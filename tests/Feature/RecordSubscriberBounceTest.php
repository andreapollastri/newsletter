<?php

namespace Tests\Feature;

use App\Enums\SubscriberStatus;
use App\Models\Bounce;
use App\Models\MessageClick;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Services\RecordSubscriberBounce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordSubscriberBounceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_bounce_and_marks_the_subscriber_bounced(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create([
            'email' => 'bounce@example.com',
        ]);
        $messageSend = MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
        ]);

        $bounce = app(RecordSubscriberBounce::class)->handle(
            $subscriber,
            'hard',
            'mailbox not found for bounce@example.com',
        );

        $this->assertDatabaseHas('bounces', [
            'id' => $bounce->id,
            'email' => $subscriber->email,
            'message_send_id' => $messageSend->id,
            'type' => 'hard',
        ]);
        $this->assertSame(SubscriberStatus::Bounced, $subscriber->fresh()->status);
    }

    public function test_it_discards_opens_and_clicks_for_the_failed_send(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create();
        $messageSend = MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'opens_count' => 1,
            'clicks_count' => 1,
        ]);

        MessageOpen::query()->create([
            'message_send_id' => $messageSend->id,
            'opened_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        MessageClick::query()->create([
            'message_send_id' => $messageSend->id,
            'url' => 'https://example.com',
            'clicked_at' => now(),
            'ip_address' => '127.0.0.1',
        ]);

        app(RecordSubscriberBounce::class)->handle(
            $subscriber,
            'hard',
            'undelivered mail returned to sender',
        );

        $messageSend->refresh();

        $this->assertSame(0, $messageSend->opens_count);
        $this->assertSame(0, $messageSend->clicks_count);
        $this->assertDatabaseCount('message_opens', 0);
        $this->assertDatabaseCount('message_clicks', 0);
    }

    public function test_it_links_the_bounce_using_the_tracking_pixel_in_the_ndr(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create([
            'email' => 'bounce@example.com',
        ]);

        MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'sent_at' => now(),
        ]);

        $originalSend = MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'sent_at' => now()->subMinute(),
        ]);

        $ndr = 'Delivery Status Notification'."\n"
            .'Final-Recipient: rfc822; bounce@example.com'."\n"
            .'<img src="https://newsletter.test/track/open/'.$originalSend->id.'" />';

        $bounce = app(RecordSubscriberBounce::class)->handle(
            $subscriber,
            'hard',
            $ndr,
            $ndr,
        );

        $this->assertSame($originalSend->id, $bounce->message_send_id);
    }

    public function test_it_does_not_duplicate_an_existing_bounce_for_the_same_send(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create();
        $messageSend = MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'opens_count' => 1,
        ]);

        MessageOpen::query()->create([
            'message_send_id' => $messageSend->id,
            'opened_at' => now(),
        ]);

        Bounce::create([
            'message_send_id' => $messageSend->id,
            'email' => $subscriber->email,
            'type' => 'hard',
            'raw_message' => 'first bounce',
            'detected_at' => now()->subMinute(),
        ]);

        $existing = app(RecordSubscriberBounce::class)->handle(
            $subscriber,
            'hard',
            'second copy of the same ndr',
        );

        $this->assertDatabaseCount('bounces', 1);
        $this->assertSame($messageSend->id, $existing->message_send_id);
        $this->assertSame(SubscriberStatus::Bounced, $subscriber->fresh()->status);
        $this->assertDatabaseCount('message_opens', 0);
        $this->assertSame(0, $messageSend->fresh()->opens_count);
    }
}
