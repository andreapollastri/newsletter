<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Tag;
use App\Models\User;
use App\Services\MessageCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageCompletionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_message_as_sent_when_all_sends_are_terminal(): void
    {
        User::factory()->create();

        $message = Message::factory()->create([
            'status' => MessageStatus::Sending,
        ]);

        MessageSend::factory()->create([
            'message_id' => $message->id,
            'sent_at' => now(),
            'failed_at' => null,
        ]);

        MessageSend::factory()->create([
            'message_id' => $message->id,
            'sent_at' => null,
            'failed_at' => now(),
            'error_message' => 'SMTP error',
        ]);

        $completed = app(MessageCompletionService::class)->completeIfFinished($message);

        $this->assertTrue($completed);
        $message->refresh();
        $this->assertSame(MessageStatus::Sent, $message->status);
        $this->assertNotNull($message->sent_at);
    }

    public function test_it_does_not_complete_when_pending_sends_remain(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::Sending,
        ]);

        MessageSend::factory()->create([
            'message_id' => $message->id,
            'sent_at' => now(),
        ]);

        MessageSend::factory()->create([
            'message_id' => $message->id,
            'sent_at' => null,
            'failed_at' => null,
        ]);

        $completed = app(MessageCompletionService::class)->completeIfFinished($message);

        $this->assertFalse($completed);
        $message->refresh();
        $this->assertSame(MessageStatus::Sending, $message->status);
    }

    public function test_it_is_idempotent_when_already_sent(): void
    {
        $message = Message::factory()->create([
            'status' => MessageStatus::Sent,
            'sent_at' => now()->subMinute(),
        ]);

        MessageSend::factory()->create([
            'message_id' => $message->id,
            'sent_at' => now(),
        ]);

        $completed = app(MessageCompletionService::class)->completeIfFinished($message);

        $this->assertFalse($completed);
    }

    public function test_it_purges_testing_audience_sends_after_completion(): void
    {
        User::factory()->create();

        $tag = Tag::factory()->testing()->create();
        $message = Message::factory()->create([
            'status' => MessageStatus::Sending,
        ]);
        $message->tags()->attach($tag->id);

        MessageSend::factory()->create([
            'message_id' => $message->id,
            'sent_at' => now(),
        ]);

        app(MessageCompletionService::class)->completeIfFinished($message);

        $this->assertDatabaseCount('message_sends', 0);
    }
}
