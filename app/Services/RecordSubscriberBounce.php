<?php

namespace App\Services;

use App\Enums\SubscriberStatus;
use App\Models\Bounce;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;

class RecordSubscriberBounce
{
    public function __construct(private ImapBounceDetector $detector) {}

    /**
     * Record a bounce and discard engagement on the failed send.
     *
     * Non-delivery reports typically include the original HTML, so mail clients and
     * scanners load the tracking pixel and would otherwise count a false open.
     */
    public function handle(Subscriber $subscriber, string $type, string $rawMessage, string $content = ''): Bounce
    {
        return DB::transaction(function () use ($subscriber, $type, $rawMessage, $content): Bounce {
            $messageSendId = $this->detector->resolveMessageSendId($subscriber, $content);

            $messageSend = $messageSendId !== null
                ? MessageSend::query()->lockForUpdate()->find($messageSendId)
                : null;

            $existing = $messageSend !== null
                ? Bounce::query()->where('message_send_id', $messageSend->id)->first()
                : null;

            if ($existing !== null) {
                $messageSend->discardEngagementTracking();
                $this->markSubscriberBounced($subscriber);

                return $existing;
            }

            $bounce = Bounce::create([
                'message_send_id' => $messageSend?->id,
                'email' => $subscriber->email,
                'type' => $type,
                'raw_message' => substr($rawMessage, 0, 5000),
                'detected_at' => now(),
            ]);

            $messageSend?->discardEngagementTracking();
            $this->markSubscriberBounced($subscriber);

            return $bounce;
        });
    }

    private function markSubscriberBounced(Subscriber $subscriber): void
    {
        $subscriber->update([
            'status' => SubscriberStatus::Bounced,
        ]);
    }
}
