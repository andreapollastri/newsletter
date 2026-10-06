<?php

namespace App\Services;

use App\Models\MessageSend;
use App\Models\Subscriber;
use Illuminate\Support\Str;

class ImapBounceDetector
{
    /**
     * @var list<string>
     */
    private const BOUNCE_INDICATORS = [
        'delivery status notification',
        'undelivered mail',
        'undeliverable',
        'mail delivery failed',
        'returned mail',
        'returned to sender',
        'delivery failure',
        'bounce',
        'non-delivery report',
    ];

    /**
     * @var list<string>
     */
    private const HARD_BOUNCE_INDICATORS = [
        'user unknown',
        'mailbox not found',
        'address rejected',
        'does not exist',
        'no such user',
        'invalid recipient',
        'recipient rejected',
    ];

    /**
     * @var list<string>
     */
    private const DELAY_INDICATORS = [
        'delivery status notification (delay)',
        'delayed mail',
        'delivery delayed',
        'still being retried',
        'this is a warning only',
    ];

    /**
     * Parse RFC 3464 per-recipient delivery-status fields (multipart/report NDRs from Postfix, Exim, Gmail, Exchange…).
     *
     * @return list<array{recipient: string, action: string, status: string|null}>
     */
    public function parseDeliveryStatus(string $content): array
    {
        $recipients = [];

        foreach (preg_split('/\R[ \t]*\R/', $content) ?: [] as $block) {
            if (preg_match('/^(?:Final|Original)-Recipient:\s*rfc822;\s*<?([^\s<>]+@[^\s<>]+)>?/im', $block, $recipient) !== 1) {
                continue;
            }

            preg_match('/^Final-Recipient:\s*rfc822;\s*<?([^\s<>]+@[^\s<>]+)>?/im', $block, $finalRecipient);
            preg_match('/^Action:\s*([a-z]+)/im', $block, $action);
            preg_match('/^Status:\s*([245]\.\d{1,3}\.\d{1,3})/im', $block, $status);

            $recipients[] = [
                'recipient' => strtolower($finalRecipient[1] ?? $recipient[1]),
                'action' => strtolower($action[1] ?? 'failed'),
                'status' => $status[1] ?? null,
            ];
        }

        return $recipients;
    }

    /**
     * Recipients whose delivery failed according to a bounce report.
     *
     * Structured DSN fields win over keyword heuristics: only "Action: failed" recipients count, and the
     * enhanced status code decides hard vs soft. Delay notices and successful DSNs yield no failures.
     * Without DSN fields, every address in the report is a candidate except our own (sender, mailbox),
     * which appear in the returned original message.
     *
     * @param  array<int, string|null>  $ignoredAddresses
     * @return list<array{email: string, type: string}>
     */
    public function detectFailedRecipients(string $subject, string $body, array $ignoredAddresses = []): array
    {
        $deliveryStatus = $this->parseDeliveryStatus($body);

        if ($deliveryStatus !== []) {
            return collect($deliveryStatus)
                ->where('action', 'failed')
                ->unique('recipient')
                ->map(fn (array $entry): array => [
                    'email' => $entry['recipient'],
                    'type' => $this->bounceTypeFromStatus($entry['status']) ?? $this->detectBounceType($body),
                ])
                ->values()
                ->all();
        }

        if (! $this->isBounceLikely($subject, $body) || $this->isDelayNotice($subject, $body)) {
            return [];
        }

        $ignored = array_map(strtolower(...), array_filter($ignoredAddresses));
        $type = $this->detectBounceType($body);

        return collect($this->extractEmailAddresses($body))
            ->reject(fn (string $email): bool => in_array(strtolower($email), $ignored, true))
            ->map(fn (string $email): array => ['email' => $email, 'type' => $type])
            ->values()
            ->all();
    }

    /**
     * Delay/warning notices mean the MTA is still retrying: they are not bounces.
     */
    public function isDelayNotice(string $subject, string $body): bool
    {
        foreach (self::DELAY_INDICATORS as $indicator) {
            if (stripos($subject, $indicator) !== false || stripos($body, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map an enhanced status code (RFC 3463) to a bounce type: 5.x.x is permanent, 4.x.x transient.
     */
    public function bounceTypeFromStatus(?string $status): ?string
    {
        return match ($status === null ? null : $status[0]) {
            '5' => 'hard',
            '4' => 'soft',
            default => null,
        };
    }

    public function isBounceLikely(string $subject, string $body): bool
    {
        foreach (self::BOUNCE_INDICATORS as $indicator) {
            if (stripos($subject, $indicator) !== false || stripos($body, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function extractEmailAddresses(string $content): array
    {
        $pattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        preg_match_all($pattern, $content, $matches);

        /** @var list<string> $emails */
        $emails = array_values(array_unique($matches[0] ?? []));

        return array_values(array_filter(
            $emails,
            fn (string $email): bool => ! str_contains(strtolower($email), 'postmaster@')
                && ! str_contains(strtolower($email), 'mailer-daemon@')
        ));
    }

    public function detectBounceType(string $content): string
    {
        foreach (self::HARD_BOUNCE_INDICATORS as $indicator) {
            if (stripos($content, $indicator) !== false) {
                return 'hard';
            }
        }

        return 'soft';
    }

    /**
     * Prefer a tracking URL from the NDR body, otherwise the most recent successful send.
     */
    public function resolveMessageSendId(Subscriber $subscriber, string $content = ''): ?string
    {
        $extractedId = $this->extractMessageSendIdFromContent($content);

        if ($extractedId !== null) {
            $belongsToSubscriber = MessageSend::query()
                ->whereKey($extractedId)
                ->where('subscriber_id', $subscriber->id)
                ->exists();

            if ($belongsToSubscriber) {
                return $extractedId;
            }
        }

        return MessageSend::query()
            ->where('subscriber_id', $subscriber->id)
            ->whereNotNull('sent_at')
            ->whereNull('failed_at')
            ->latest('sent_at')
            ->value('id');
    }

    /**
     * Pull the original message-send UUID from a tracking pixel or wrapped click URL.
     */
    public function extractMessageSendIdFromContent(string $content): ?string
    {
        // Returned originals are often quoted-printable encoded, which splits long URLs with soft line breaks.
        foreach ([$content, quoted_printable_decode($content)] as $candidate) {
            $decoded = urldecode(html_entity_decode($candidate, ENT_QUOTES));

            if (preg_match('#/track/(?:open|click)/([0-9a-fA-F-]{36})#', $decoded, $matches) === 1 && Str::isUuid($matches[1])) {
                return $matches[1];
            }
        }

        return null;
    }
}
