<?php

namespace Tests\Feature\Jobs;

use App\Enums\MessageStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendNewsletterEmailRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected Subscriber $subscriber;

    protected Message $message;

    protected MessageSend $messageSend;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user for notifications
        User::factory()->create();

        // Create test data
        $campaign = Campaign::factory()->create();
        $template = Template::factory()->create();
        $this->subscriber = Subscriber::factory()->create();

        $this->message = Message::factory()->create([
            'campaign_id' => $campaign->id,
            'template_id' => $template->id,
            'status' => MessageStatus::Sending,
        ]);

        $this->messageSend = MessageSend::create([
            'message_id' => $this->message->id,
            'subscriber_id' => $this->subscriber->id,
        ]);

        // Clear cache
        Cache::flush();

        // Fake mail
        Mail::fake();
    }

    public function test_job_sends_email_when_no_rate_limit_configured(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        $job = new SendNewsletterEmail($this->messageSend->id);
        $job->handle(app(\App\Services\EmailRateLimiter::class));

        $this->messageSend->refresh();
        $this->assertNotNull($this->messageSend->sent_at);
    }

    public function test_job_releases_when_rate_limit_reached(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 1);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 0);

        // First job should send
        $job1 = new SendNewsletterEmail($this->messageSend->id);
        $job1->handle(app(\App\Services\EmailRateLimiter::class));

        $this->messageSend->refresh();
        $this->assertNotNull($this->messageSend->sent_at);

        // Create another message send
        $messageSend2 = MessageSend::create([
            'message_id' => $this->message->id,
            'subscriber_id' => Subscriber::factory()->create()->id,
        ]);

        // Second job should be released (not sent)
        Queue::fake();
        $job2 = new SendNewsletterEmail($messageSend2->id);
        $job2->handle(app(\App\Services\EmailRateLimiter::class));

        $messageSend2->refresh();
        $this->assertNull($messageSend2->sent_at);
    }

    public function test_job_respects_hourly_limit(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 1);
        Config::set('newsletter.rate_limits.per_day', 0);

        // First job should send
        $job1 = new SendNewsletterEmail($this->messageSend->id);
        $job1->handle(app(\App\Services\EmailRateLimiter::class));

        $this->messageSend->refresh();
        $this->assertNotNull($this->messageSend->sent_at);

        // Create another message send
        $messageSend2 = MessageSend::create([
            'message_id' => $this->message->id,
            'subscriber_id' => Subscriber::factory()->create()->id,
        ]);

        // Second job should be released
        Queue::fake();
        $job2 = new SendNewsletterEmail($messageSend2->id);
        $job2->handle(app(\App\Services\EmailRateLimiter::class));

        $messageSend2->refresh();
        $this->assertNull($messageSend2->sent_at);
    }

    public function test_job_respects_daily_limit(): void
    {
        Config::set('newsletter.rate_limits.per_minute', 0);
        Config::set('newsletter.rate_limits.per_hour', 0);
        Config::set('newsletter.rate_limits.per_day', 1);

        // First job should send
        $job1 = new SendNewsletterEmail($this->messageSend->id);
        $job1->handle(app(\App\Services\EmailRateLimiter::class));

        $this->messageSend->refresh();
        $this->assertNotNull($this->messageSend->sent_at);

        // Create another message send
        $messageSend2 = MessageSend::create([
            'message_id' => $this->message->id,
            'subscriber_id' => Subscriber::factory()->create()->id,
        ]);

        // Second job should be released
        Queue::fake();
        $job2 = new SendNewsletterEmail($messageSend2->id);
        $job2->handle(app(\App\Services\EmailRateLimiter::class));

        $messageSend2->refresh();
        $this->assertNull($messageSend2->sent_at);
    }
}
