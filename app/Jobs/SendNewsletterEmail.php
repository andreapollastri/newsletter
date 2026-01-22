<?php

namespace App\Jobs;

use App\Enums\MessageStatus;
use App\Mail\NewsletterMail;
use App\Models\MessageSend;
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
    public function handle(): void
    {
        $messageSend = MessageSend::with(['message.template', 'subscriber'])->find($this->messageSendId);

        if (! $messageSend) {
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
            $subject = $this->replacePlaceholders($message->subject, $subscriber);
            $htmlContent = $this->replacePlaceholders($htmlContent, $subscriber);

            // Add unsubscribe footer
            $htmlContent = $this->addUnsubscribeFooter($htmlContent, $subscriber);

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

    protected function replacePlaceholders(string $content, $subscriber): string
    {
        $unsubscribeUrl = route('unsubscribe', $subscriber->id);

        return str_replace(
            ['{{name}}', '{{email}}', '{{unsubscribe_url}}'],
            [$subscriber->name ?? '', $subscriber->email, $unsubscribeUrl],
            $content
        );
    }

    protected function addUnsubscribeFooter(string $content, $subscriber): string
    {
        $unsubscribeUrl = route('unsubscribe', $subscriber->id);

        $footer = <<<HTML
<div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e5e5; text-align: center; font-size: 12px; color: #666;">
    <p style="margin: 0;">
        Non vuoi più ricevere queste email? 
        <a href="{$unsubscribeUrl}" style="color: #666; text-decoration: underline;">Clicca qui per disiscriverti</a>
    </p>
</div>
HTML;

        // Try to insert before </body>
        if (str_contains($content, '</body>')) {
            return str_replace('</body>', $footer.'</body>', $content);
        }

        // Otherwise append at the end
        return $content.$footer;
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

        if ($pendingSends === 0 && $message->status === MessageStatus::Sending) {
            $message->update([
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
            ]);
        }
    }
}
