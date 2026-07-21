<?php

namespace App\Jobs;

use App\Enums\SubscriberStatus;
use App\Models\Bounce;
use App\Models\Subscriber;
use App\Services\ImapBounceDetector;
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
    public function handle(ImapBounceDetector $detector): void
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
                $this->processMessage($message, $detector);
            }

            $client->disconnect();
        } catch (\Throwable $e) {
            Log::error('IMAP bounce processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function processMessage(mixed $message, ImapBounceDetector $detector): void
    {
        $subject = (string) $message->getSubject();
        $body = (string) ($message->getTextBody() ?? $message->getHTMLBody() ?? '');

        if (! $detector->isBounceLikely($subject, $body)) {
            return;
        }

        $emails = $detector->extractEmailAddresses($body);
        $bounceType = $detector->detectBounceType($body);
        $rawMessage = substr($body, 0, 5000);

        foreach ($emails as $email) {
            $subscriber = Subscriber::query()->where('email', $email)->first();

            if (! $subscriber) {
                continue;
            }

            $messageSendId = $detector->resolveMessageSendId($subscriber);

            if ($messageSendId !== null && Bounce::query()->where('message_send_id', $messageSendId)->exists()) {
                $subscriber->update([
                    'status' => SubscriberStatus::Bounced,
                ]);

                continue;
            }

            Bounce::create([
                'message_send_id' => $messageSendId,
                'email' => $email,
                'type' => $bounceType,
                'raw_message' => $rawMessage,
                'detected_at' => now(),
            ]);

            $subscriber->update([
                'status' => SubscriberStatus::Bounced,
            ]);

            Log::info("Bounce detected for email: {$email}", [
                'message_send_id' => $messageSendId,
                'type' => $bounceType,
            ]);
        }

        $message->setFlag('Seen');
    }
}
