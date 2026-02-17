<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\MessageSend;
use App\Models\User;
use App\Services\EmailRateLimiter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNewsletterEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $messageSendId
    ) {
        $this->onQueue('newsletters');
    }

    /**
     * Execute the job.
     */
    public function handle(EmailRateLimiter $rateLimiter): void
    {
        $messageSend = MessageSend::with(['message.template', 'subscriber'])->find($this->messageSendId);

        if (! $messageSend) {
            return;
        }

        // Check rate limits before sending
        $rateLimitCheck = $rateLimiter->attempt();

        if (! $rateLimitCheck['allowed']) {
            // Rate limit exceeded, release the job back to the queue
            $retryAfter = $rateLimitCheck['retry_after'] ?? 60;
            $this->release($retryAfter);

            return;
        }

        $message = $messageSend->message;
        $subscriber = $messageSend->subscriber;

        try {
            // Build the final HTML content
            $htmlContent = $this->buildHtmlContent($message);

            // Convert relative image URLs to absolute URLs
            $htmlContent = $this->convertToAbsoluteUrls($htmlContent);

            // Replace placeholders
            $subject = $this->replacePlaceholders($message->subject, $subscriber, $messageSend);
            $htmlContent = $this->replacePlaceholders($htmlContent, $subscriber, $messageSend);

            // Add tracking pixel
            if (config('newsletter.tracking.enabled', true)) {
                $trackingPixel = '<img src="'.route('tracking.open', $messageSend->id).'" width="1" height="1" style="display:none;" alt="" />';
                $htmlContent = str_replace('</body>', $trackingPixel.'</body>', $htmlContent);

                // If no body tag, append at the end
                if (! str_contains($htmlContent, '</body>')) {
                    $htmlContent .= $trackingPixel;
                }

                // Wrap links for tracking
                $htmlContent = $this->wrapLinksForTracking($htmlContent, $messageSend->id);
            }

            // Send email
            Mail::to($subscriber->email)->send(new NewsletterMail($subject, $htmlContent));

            // Mark as sent
            $messageSend->update([
                'sent_at' => now(),
            ]);

            // Check if all sends are complete
            $this->checkMessageCompletion($message);
        } catch (Throwable $e) {
            // Mark as failed
            $messageSend->update([
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Build the final HTML content by merging template with message body.
     */
    protected function buildHtmlContent($message): string
    {
        $messageBody = $message->html_content;

        // If there's a template, merge the body into it
        if ($message->template) {
            $templateHtml = $message->template->html_content;

            // Replace {{body}} placeholder with message content
            if (str_contains($templateHtml, '{{body}}')) {
                return str_replace('{{body}}', $messageBody, $templateHtml);
            }

            // If no {{body}} placeholder, append message to template
            return $templateHtml.$messageBody;
        }

        // No template, return message body wrapped in basic HTML
        return $messageBody;
    }

    /**
     * Convert relative URLs to absolute URLs for images.
     */
    protected function convertToAbsoluteUrls(string $content): string
    {
        $baseUrl = config('app.url');

        // Convert relative src attributes to absolute
        $content = preg_replace_callback(
            '/src=["\'](?!https?:\/\/)([^"\']+)["\']/i',
            function ($matches) use ($baseUrl) {
                $path = ltrim($matches[1], '/');

                return 'src="'.$baseUrl.'/storage/'.$path.'"';
            },
            $content
        );

        return $content;
    }

    protected function replacePlaceholders(string $content, $subscriber, $messageSend = null): string
    {
        $unsubscribeUrl = route('unsubscribe', $subscriber->id);

        // Add message_send_id as query parameter if available
        if ($messageSend) {
            $unsubscribeUrl .= '?message_send='.$messageSend->id;
        }

        return str_replace(
            ['{{name}}', '{{email}}', '{{unsubscribe_url}}'],
            [$subscriber->name ?? '', $subscriber->email, $unsubscribeUrl],
            $content
        );
    }

    protected function wrapLinksForTracking(string $content, string $messageSendId): string
    {
        return preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) use ($messageSendId) {
                $url = $matches[2];

                // Skip tracking URLs and unsubscribe URLs
                if (str_contains($url, '/track/') || str_contains($url, '/unsubscribe/')) {
                    return $matches[0];
                }

                $trackingUrl = route('tracking.click', ['messageSend' => $messageSendId, 'url' => base64_encode($url)]);

                return '<a '.$matches[1].'href="'.$trackingUrl.'"'.$matches[3].'>';
            },
            $content
        );
    }

    protected function checkMessageCompletion($message): void
    {
        $pendingSends = MessageSend::where('message_id', $message->id)
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->count();

        if ($pendingSends === 0 && $message->status !== \App\Enums\MessageStatus::Sent) {
            $message->update([
                'status' => \App\Enums\MessageStatus::Sent,
                'sent_at' => now(),
            ]);

            // Send database notification to all users when sending is completed
            $totalSends = MessageSend::where('message_id', $message->id)->count();
            $sentSends = MessageSend::where('message_id', $message->id)->whereNotNull('sent_at')->count();
            $failedSends = MessageSend::where('message_id', $message->id)->whereNotNull('failed_at')->count();

            foreach (User::all() as $user) {
                Notification::make()
                    ->title(__('Sending completed'))
                    ->body(__('Message ":subject" sent to :sent recipients (:failed failed).', [
                        'subject' => $message->subject,
                        'sent' => $sentSends,
                        'failed' => $failedSends,
                    ]))
                    ->success()
                    ->sendToDatabase($user);
            }
        }
    }
}
