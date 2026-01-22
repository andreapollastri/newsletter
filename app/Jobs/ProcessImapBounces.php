<?php

namespace App\Jobs;

use App\Enums\SubscriberStatus;
use App\Models\Bounce;
use App\Models\Subscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class ProcessImapBounces implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $config = config('newsletter.imap');

        if (empty($config['host']) || empty($config['username']) || empty($config['password'])) {
            Log::info('IMAP configuration not set, skipping bounce processing.');

            return;
        }

        try {
            $client = Client::make([
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
                $this->processMessage($message);
            }

            $client->disconnect();
        } catch (\Throwable $e) {
            Log::error('IMAP bounce processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function processMessage($message): void
    {
        $subject = $message->getSubject();
        $body = $message->getTextBody() ?? $message->getHTMLBody();

        // Check if it's a bounce message
        $bounceIndicators = [
            'delivery status notification',
            'undeliverable',
            'mail delivery failed',
            'returned mail',
            'delivery failure',
            'bounce',
            'non-delivery report',
        ];

        $isBounceLikely = false;
        foreach ($bounceIndicators as $indicator) {
            if (stripos($subject, $indicator) !== false || stripos($body, $indicator) !== false) {
                $isBounceLikely = true;
                break;
            }
        }

        if (! $isBounceLikely) {
            return;
        }

        // Extract email addresses from the message
        $emails = $this->extractEmailAddresses($body);

        foreach ($emails as $email) {
            // Skip common system emails
            if (str_contains($email, 'postmaster@') || str_contains($email, 'mailer-daemon@')) {
                continue;
            }

            // Check if subscriber exists
            $subscriber = Subscriber::where('email', $email)->first();

            if ($subscriber) {
                // Create bounce record
                Bounce::create([
                    'email' => $email,
                    'type' => $this->detectBounceType($body),
                    'raw_message' => substr($body, 0, 5000), // Limit message size
                    'detected_at' => now(),
                ]);

                // Mark subscriber as bounced
                $subscriber->update([
                    'status' => SubscriberStatus::Bounced,
                ]);

                Log::info("Bounce detected for email: {$email}");
            }
        }

        // Mark message as read
        $message->setFlag('Seen');
    }

    protected function extractEmailAddresses(string $content): array
    {
        $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        preg_match_all($pattern, $content, $matches);

        return array_unique($matches[0] ?? []);
    }

    protected function detectBounceType(string $content): string
    {
        $hardBounceIndicators = [
            'user unknown',
            'mailbox not found',
            'address rejected',
            'does not exist',
            'no such user',
            'invalid recipient',
            'recipient rejected',
        ];

        foreach ($hardBounceIndicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                return 'hard';
            }
        }

        return 'soft';
    }
}
