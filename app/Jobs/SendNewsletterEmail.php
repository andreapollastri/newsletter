<?php

namespace App\Jobs;

use App\Mail\NewsletterMail;
use App\Models\MessageSend;
use App\Services\EmailRateLimiter;
use App\Services\MessageCompletionService;
use App\Support\NewsletterHtml;
use App\Support\NewsletterUrlUtm;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
    public function handle(EmailRateLimiter $rateLimiter, MessageCompletionService $completionService): void
    {
        $messageSend = MessageSend::with(['message.template', 'message.campaign', 'subscriber'])->find($this->messageSendId);

        if (! $messageSend) {
            return;
        }

        // Check rate limits before sending
        $rateLimitCheck = $rateLimiter->attempt();

        if (! $rateLimitCheck['allowed']) {
            // Rate limit exceeded: requeue with delay (no send, counters not incremented for this attempt).
            $retryAfter = max(1, (int) ($rateLimitCheck['retry_after'] ?? 60));
            $this->release($retryAfter);

            return;
        }

        $message = $messageSend->message;
        $subscriber = $messageSend->subscriber;

        try {
            // Merge the body into the template and make image URLs absolute
            $htmlContent = NewsletterHtml::absolutizeImageSources(NewsletterHtml::compose($message));

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

                // Wrap links for tracking (UTM params are applied to the destination URL before encoding)
                $htmlContent = $this->wrapLinksForTracking(
                    $htmlContent,
                    $messageSend->id,
                    (string) ($message->campaign?->slug ?? ''),
                    (string) $message->id
                );
            }

            $unsubscribeUrl = route('unsubscribe', $subscriber->id).'?message_send='.$messageSend->id;

            // Send email
            Mail::to($subscriber->email)->send(new NewsletterMail($subject, $htmlContent, $unsubscribeUrl));

            // Mark as sent
            $messageSend->update([
                'sent_at' => now(),
            ]);

            $completionService->completeIfFinished($message);
        } catch (Throwable $e) {
            // Mark as failed
            $messageSend->update([
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function replacePlaceholders(string $content, $subscriber, $messageSend = null): string
    {
        $unsubscribeUrl = route('unsubscribe', $subscriber->id);

        // Add message_send_id as query parameter if available
        if ($messageSend) {
            $unsubscribeUrl .= '?message_send='.$messageSend->id;
        }

        return NewsletterHtml::fillPlaceholders($content, $subscriber->name ?? '', $subscriber->email, $unsubscribeUrl);
    }

    /**
     * Click-tracking URL whose signature binds the destination to this send, so the redirect cannot be
     * reused as an open redirect. The signature is relative so it survives scheme/host differences
     * between the queue worker and the web server (proxies, APP_URL drift).
     */
    protected function signedClickUrl(string $messageSendId, string $destinationUrl): string
    {
        return url(URL::signedRoute('tracking.click', [
            'messageSend' => $messageSendId,
            'url' => base64_encode($destinationUrl),
        ], absolute: false));
    }

    protected function wrapLinksForTracking(string $content, string $messageSendId, string $campaignSlug, string $messageId): string
    {
        return preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*)>/i',
            function ($matches) use ($messageSendId, $campaignSlug, $messageId) {
                // Attribute values are HTML-encoded (e.g. "&amp;" between query parameters).
                $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5);

                // Only http(s) links can be redirected; mailto:, tel: and #anchors stay untouched.
                if (preg_match('#^https?://#i', $url) !== 1) {
                    return $matches[0];
                }

                // Skip tracking URLs and unsubscribe URLs
                if (str_contains($url, '/track/') || str_contains($url, '/unsubscribe/')) {
                    return $matches[0];
                }

                $urlWithUtm = NewsletterUrlUtm::append($url, $campaignSlug, $messageId);

                return '<a '.$matches[1].'href="'.e($this->signedClickUrl($messageSendId, $urlWithUtm)).'"'.$matches[3].'>';
            },
            $content
        );
    }
}
