<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\MessageDispatchService;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MessageDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        NotificationFacade::fake();
    }

    public function test_dispatch_queues_one_send_per_target_subscriber(): void
    {
        $user = User::factory()->create();
        $message = Message::factory()->ready()->create();
        Subscriber::factory()->confirmed()->count(3)->create();
        Subscriber::factory()->create(['status' => 'unsubscribed']);

        $queued = app(MessageDispatchService::class)->dispatch($message);

        $this->assertSame(3, $queued);
        $this->assertSame(MessageStatus::Sending, $message->fresh()->status);
        $this->assertSame(3, $message->sends()->count());
        Queue::assertPushed(SendNewsletterEmail::class, 3);
        Queue::assertPushedOn('newsletters', SendNewsletterEmail::class);
        NotificationFacade::assertSentToTimes($user, DatabaseNotification::class, 1);
    }

    public function test_dispatch_is_idempotent_for_subscribers_already_queued(): void
    {
        $message = Message::factory()->ready()->create();
        $alreadyQueued = Subscriber::factory()->confirmed()->create();
        Subscriber::factory()->confirmed()->create();
        MessageSend::factory()->create([
            'message_id' => $message->id,
            'subscriber_id' => $alreadyQueued->id,
        ]);

        $queued = app(MessageDispatchService::class)->dispatch($message);

        $this->assertSame(1, $queued);
        $this->assertSame(2, $message->sends()->count());
        $this->assertSame(1, $message->sends()->where('subscriber_id', $alreadyQueued->id)->count());
    }

    public function test_dispatch_without_recipients_leaves_message_untouched(): void
    {
        $tag = Tag::factory()->create();
        $message = Message::factory()->ready()->create();
        $message->tags()->attach($tag->id);
        Subscriber::factory()->confirmed()->create();

        $queued = app(MessageDispatchService::class)->dispatch($message);

        $this->assertSame(0, $queued);
        $this->assertSame(MessageStatus::Ready, $message->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_scheduled_message_without_recipients_returns_to_draft(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $message = Message::factory()->create([
            'status' => MessageStatus::Ready,
            'scheduled_at' => now()->subMinute(),
        ]);
        $message->tags()->attach($tag->id);

        $this->artisan('newsletter:send-scheduled')->assertSuccessful();

        $this->assertSame(MessageStatus::Draft, $message->fresh()->status);
        NotificationFacade::assertSentToTimes($user, DatabaseNotification::class, 1);
        Queue::assertNothingPushed();
    }

    public function test_scheduled_message_is_queued_once(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::Ready,
            'scheduled_at' => now()->subMinute(),
        ]);
        Subscriber::factory()->confirmed()->count(2)->create();

        $this->artisan('newsletter:send-scheduled')->assertSuccessful();
        $this->artisan('newsletter:send-scheduled')->assertSuccessful();

        $this->assertSame(MessageStatus::Sending, $message->fresh()->status);
        $this->assertSame(2, $message->sends()->count());
        Queue::assertPushed(SendNewsletterEmail::class, 2);
    }

    public function test_send_now_without_recipients_warns_and_keeps_message_ready(): void
    {
        $this->actingAs(User::factory()->create());
        $tag = Tag::factory()->create();
        $message = Message::factory()->ready()->create();
        $message->tags()->attach($tag->id);

        Livewire::test(ListMessages::class)
            ->callTableAction('sendNow', $message)
            ->assertNotified(__('No recipients'));

        $this->assertSame(MessageStatus::Ready, $message->fresh()->status);
        Queue::assertNothingPushed();
    }
}
