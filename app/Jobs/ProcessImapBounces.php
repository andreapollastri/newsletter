<?php

namespace App\Jobs;

use App\Models\Subscriber;
use App\Services\ImapBounceDetector;
use App\Services\RecordSubscriberBounce;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessImapBounces implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    /**
     * Fully-qualified facade class from webklex/laravel-imap (optional dependency).
     */
    private const IMAP_CLIENT_FACADE = 'Webklex\\IMAP\\Facades\\Client';

    /**
     * Execute the job.
     */
    public function handle(ImapBounceDetector $detector, RecordSubscriberBounce $recorder): void
    {
        if (! config('newsletter.imap.enabled')) {
            Log::info('IMAP bounce processing is disabled (NEWSLETTER_IMAP_ENABLED=false).');

            return;
        }

        $config = config('newsletter.imap');

        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            Log::info('IMAP configuration incomplete, skipping bounce processing.');

            return;
        }

        if (! class_exists(self::IMAP_CLIENT_FACADE)) {
            Log::warning(
                'IMAP bounce processing requires webklex/laravel-imap. Install with: composer require webklex/laravel-imap'
            );

            return;
        }

        try {
            /** @var class-string $clientFacade */
            $clientFacade = self::IMAP_CLIENT_FACADE;

            $client = $clientFacade::make([
                'host' => $config['host'],
                'port' => $config['port'],
                'encryption' => $config['encryption'],
                'validate_cert' => true,
                'username' => $config['username'],
                'password' => $config['password'],
                'protocol' => 'imap',
            ]);

            $client->connect();

            $folder = $client->getFolder($config['folder'] ?? 'INBOX');
            $messages = $folder->query()
                ->unseen()
                ->get();

            foreach ($messages as $message) {
                $this->processMessage($message, $detector, $recorder);
            }

            $client->disconnect();
        } catch (\Throwable $e) {
            Log::error('IMAP bounce processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function processMessage(mixed $message, ImapBounceDetector $detector, RecordSubscriberBounce $recorder): void
    {
        $subject = (string) $message->getSubject();
        $textBody = (string) ($message->getTextBody() ?? '');
        $htmlBody = (string) ($message->getHTMLBody() ?? '');
        $body = trim($textBody."\n".$htmlBody);

        // The message/delivery-status part (Final-Recipient, Action, Status) is usually not exposed as a text
        // body, so include the raw MIME body when the client provides it.
        $content = method_exists($message, 'getRawBody')
            ? trim($body."\n".$message->getRawBody())
            : $body;

        $failures = $detector->detectFailedRecipients($subject, $content, [
            config('mail.from.address'),
            config('newsletter.imap.username'),
        ]);

        if ($failures === [] && ! $detector->isBounceLikely($subject, $content)) {
            return;
        }

        $rawMessage = substr($body !== '' ? $body : $content, 0, 5000);

        foreach ($failures as $failure) {
            $subscriber = Subscriber::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($failure['email'])])
                ->first();

            if (! $subscriber) {
                continue;
            }

            $bounce = $recorder->handle($subscriber, $failure['type'], $rawMessage, $content);

            if ($bounce->wasRecentlyCreated) {
                Log::info("Bounce detected for email: {$subscriber->email}", [
                    'message_send_id' => $bounce->message_send_id,
                    'type' => $failure['type'],
                ]);
            }
        }

        $message->setFlag('Seen');
    }
}
