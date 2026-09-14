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
        $decoded = urldecode(html_entity_decode($content, ENT_QUOTES));

        if (preg_match('#/track/(?:open|click)/([0-9a-fA-F-]{36})#', $decoded, $matches) !== 1) {
            return null;
        }

        return Str::isUuid($matches[1]) ? $matches[1] : null;
    }
}
