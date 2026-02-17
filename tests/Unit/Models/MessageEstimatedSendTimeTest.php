<?php

namespace Tests\Unit\Models;

use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MessageEstimatedSendTimeTest extends TestCase
{
    use RefreshDatabase;

    protected Campaign $campaign;

    protected Template $template;

    protected Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaign = Campaign::factory()->create();
        $this->template = Template::factory()->create();

        $this->message = Message::factory()->create([
            'campaign_id' => $this->campaign->id,
            'template_id' => $this->template->id,
            'status' => MessageStatus::Sending,
        ]);
    }

    public function test_returns_null_when_status_is_not_sending(): void
    {
        $this->message->update(['status' => MessageStatus::Draft]);

        $this->assertNull($this->message->getEstimatedSendTime());
    }

    public function test_returns_completing_when_no_pending_sends(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 60);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        $this->message->update(['status' => MessageStatus::Sending]);

        $result = $this->message->getEstimatedSendTime();

        $this->assertEquals(__('Completing...'), $result);
    }

    public function test_returns_immediate_when_no_limits_configured(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        // Create pending sends
        $subscribers = Subscriber::factory()->count(10)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        // Check for either "immediate" (English) or "immediato" (Italian)
        $this->assertTrue(
            str_contains(strtolower($result), 'immediate') || str_contains(strtolower($result), 'immediato'),
            "Expected result to contain immediate/immediato but got: {$result}"
        );
    }

    public function test_calculates_estimate_based_on_per_minute_limit(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 60);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        // Create 120 pending sends = 2 minutes at 60/min
        $subscribers = Subscriber::factory()->count(120)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('minut', strtolower($result));
    }

    public function test_calculates_estimate_based_on_hourly_limit(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 100);
        Config::set('newsletter.rate_limits.per_day', 0);

        // Create 200 pending sends = 2 hours at 100/hour
        $subscribers = Subscriber::factory()->count(200)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        $this->assertStringContainsString('2', $result);
        $this->assertStringContainsString('or', strtolower($result));
    }

    public function test_calculates_estimate_based_on_daily_limit(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 1000);

        // Create 2000 pending sends = 2 days at 1000/day
        $subscribers = Subscriber::factory()->count(2000)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        $this->assertStringContainsString('2', $result);
        // Check for either "day" (English) or "giorn" (Italian - giorno/giorni)
        $this->assertTrue(
            str_contains(strtolower($result), 'day') || str_contains(strtolower($result), 'giorn'),
            "Expected result to contain day/giorno but got: {$result}"
        );
    }

    public function test_uses_the_most_restrictive_limit(): void
    {
        // Daily limit is most restrictive: 1000/day = ~0.69/min
        // Hourly limit: 100/hour = ~1.67/min
        // Per minute limit: 10/min
        Config::set('newsletter.rate_limits.per_minute', 10);
        Config::set('newsletter.rate_limits.per_hour', 100);
        Config::set('newsletter.rate_limits.per_day', 1000);

        // Create 2000 pending sends
        // At 10/min = 200 minutes
        // At 100/hour = 1200 minutes (20 hours)
        // At 1000/day = 2880 minutes (2 days) <- most restrictive
        $subscribers = Subscriber::factory()->count(2000)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        // Should use daily limit (2 days)
        $this->assertStringContainsString('2', $result);
        // Check for either "day" (English) or "giorn" (Italian - giorno/giorni)
        $this->assertTrue(
            str_contains(strtolower($result), 'day') || str_contains(strtolower($result), 'giorn'),
            "Expected result to contain day/giorno but got: {$result}"
        );
    }

    public function test_formats_minutes_correctly(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 10);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        // Create 5 pending sends = 1 minute
        $subscribers = Subscriber::factory()->count(5)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('minut', strtolower($result));
    }

    public function test_formats_hours_correctly(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 100);
        Config::set('newsletter.rate_limits.per_day', 0);

        // Create 100 pending sends = 1 hour
        $subscribers = Subscriber::factory()->count(100)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('or', strtolower($result));
    }

    public function test_formats_days_correctly(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 1000);

        // Create 1000 pending sends = 1 day
        $subscribers = Subscriber::factory()->count(1000)->create();
        foreach ($subscribers as $subscriber) {
            MessageSend::factory()->create([
                'message_id' => $this->message->id,
                'subscriber_id' => $subscriber->id,
            ]);
        }

        $result = $this->message->getEstimatedSendTime();

        $this->assertStringContainsString('1', $result);
        // Check for either "day" (English) or "giorn" (Italian - giorno/giorni)
        $this->assertTrue(
            str_contains(strtolower($result), 'day') || str_contains(strtolower($result), 'giorn'),
            "Expected result to contain day/giorno but got: {$result}"
        );
    }
}
