<?php

namespace Tests\Feature;

use App\Enums\SubscriberStatus;
use App\Jobs\ProcessImapBounces;
use App\Models\Bounce;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Services\ImapBounceDetector;
use App\Services\RecordSubscriberBounce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProcessImapBouncesJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_skips_when_imap_is_disabled(): void
    {
        config(['newsletter.imap.enabled' => false]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'disabled'));

        $this->app->call([new ProcessImapBounces, 'handle']);
    }

    public function test_job_skips_when_package_is_missing(): void
    {
        config([
            'newsletter.imap.enabled' => true,
            'newsletter.imap.host' => 'imap.example.com',
            'newsletter.imap.username' => 'user',
            'newsletter.imap.password' => 'secret',
        ]);

        $this->assertFalse(class_exists('Webklex\\IMAP\\Facades\\Client'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'webklex/laravel-imap'));

        $this->app->call([new ProcessImapBounces, 'handle']);
    }

    public function test_detector_links_bounce_to_latest_message_send(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create([
            'email' => 'bounce@example.com',
        ]);

        MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'sent_at' => now()->subDay(),
        ]);

        $latest = MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'sent_at' => now(),
        ]);

        $detector = app(ImapBounceDetector::class);

        $this->assertTrue($detector->isBounceLikely('Mail Delivery Failed', 'User unknown'));
        $this->assertTrue($detector->isBounceLikely('Undelivered Mail Returned to Sender', ''));
        $this->assertSame('hard', $detector->detectBounceType('mailbox not found for bounce@example.com'));
        $this->assertSame([$subscriber->email], $detector->extractEmailAddresses('Failed: bounce@example.com mailer-daemon@example.com'));
        $this->assertSame($latest->id, $detector->resolveMessageSendId($subscriber));
        $this->assertSame(
            $latest->id,
            $detector->extractMessageSendIdFromContent('Failed: <img src="https://example.test/track/open/'.$latest->id.'">'),
        );
        $this->assertSame(
            $latest->id,
            $detector->resolveMessageSendId(
                $subscriber,
                'Mail Delivery Failed /track/open/'.$latest->id,
            ),
        );

        Bounce::create([
            'message_send_id' => $detector->resolveMessageSendId($subscriber),
            'email' => $subscriber->email,
            'type' => 'hard',
            'raw_message' => 'mailbox not found',
            'detected_at' => now(),
        ]);

        $this->assertDatabaseHas('bounces', [
            'email' => $subscriber->email,
            'message_send_id' => $latest->id,
            'type' => 'hard',
        ]);
    }

    public function test_process_bounces_command_exits_cleanly_when_disabled(): void
    {
        config(['newsletter.imap.enabled' => false]);

        $this->artisan('newsletter:process-bounces')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_detector_prefers_tracking_pixel_over_latest_send(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create();

        MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'sent_at' => now(),
        ]);

        $original = MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'sent_at' => now()->subMinute(),
        ]);

        $detector = app(ImapBounceDetector::class);

        $this->assertSame(
            $original->id,
            $detector->resolveMessageSendId(
                $subscriber,
                'Undelivered Mail Returned to Sender https://example.test/track/click/'.$original->id.'?url=abc',
            ),
        );
    }

    public function test_detector_ignores_tracking_pixel_belonging_to_another_subscriber(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create();
        $latest = MessageSend::factory()->sent()->create([
            'subscriber_id' => $subscriber->id,
            'sent_at' => now(),
        ]);
        $foreign = MessageSend::factory()->sent()->create();

        $detector = app(ImapBounceDetector::class);

        $this->assertSame(
            $latest->id,
            $detector->resolveMessageSendId($subscriber, '/track/open/'.$foreign->id),
        );
    }

    public function test_job_bounces_only_the_failed_dsn_recipient(): void
    {
        $recipient = Subscriber::factory()->confirmed()->create(['email' => 'recipient@example.com']);
        $owner = Subscriber::factory()->confirmed()->create(['email' => 'owner@example.com']);
        $send = MessageSend::factory()->sent()->create(['subscriber_id' => $recipient->id, 'opens_count' => 1]);

        $message = $this->fakeImapMessage(
            'Undelivered Mail Returned to Sender',
            "<recipient@example.com>: host mx.example.com said: 550 5.1.1 User unknown\n\n"
            ."Final-Recipient: rfc822; recipient@example.com\nAction: failed\nStatus: 5.1.1\n\n"
            ."From: Owner <owner@example.com>\nTo: recipient@example.com\n"
            .'<img src="https://news.example.com/track/open/'.$send->id.'">',
        );

        $this->processImapMessage($message);

        $this->assertSame(SubscriberStatus::Bounced, $recipient->fresh()->status);
        $this->assertSame(SubscriberStatus::Confirmed, $owner->fresh()->status);
        $this->assertDatabaseHas('bounces', ['message_send_id' => $send->id, 'type' => 'hard']);
        $this->assertSame(0, $send->fresh()->opens_count);
        $this->assertSame(['Seen'], $message->flags);
    }

    public function test_job_ignores_delay_notifications(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create(['email' => 'slow@example.com']);

        $message = $this->fakeImapMessage(
            'Delivery Status Notification (Delay)',
            "Final-Recipient: rfc822; slow@example.com\nAction: delayed\nStatus: 4.4.1",
        );

        $this->processImapMessage($message);

        $this->assertSame(SubscriberStatus::Confirmed, $subscriber->fresh()->status);
        $this->assertDatabaseCount('bounces', 0);
    }

    private function processImapMessage(object $message): void
    {
        (new \ReflectionMethod(ProcessImapBounces::class, 'processMessage'))->invoke(
            new ProcessImapBounces,
            $message,
            app(ImapBounceDetector::class),
            app(RecordSubscriberBounce::class),
        );
    }

    /**
     * Minimal stand-in for a webklex/php-imap message.
     */
    private function fakeImapMessage(string $subject, string $body): object
    {
        return new class($subject, $body)
        {
            /** @var list<string> */
            public array $flags = [];

            public function __construct(private string $subject, private string $body) {}

            public function getSubject(): string
            {
                return $this->subject;
            }

            public function getTextBody(): string
            {
                return $this->body;
            }

            public function getHTMLBody(): ?string
            {
                return null;
            }

            public function setFlag(string $flag): void
            {
                $this->flags[] = $flag;
            }
        };
    }
}
