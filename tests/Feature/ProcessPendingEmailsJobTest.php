<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Jobs\ProcessPendingEmails;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessPendingEmailsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_send_jobs_for_pending_message_sends(): void
    {
        Queue::fake([SendNewsletterEmail::class]);

        $pending = MessageSend::factory()->create([
            'sent_at' => null,
            'failed_at' => null,
        ]);

        MessageSend::factory()->sent()->create();

        $this->app->call([new ProcessPendingEmails, 'handle']);

        Queue::assertPushed(SendNewsletterEmail::class, function (SendNewsletterEmail $job) use ($pending): bool {
            return $job->messageSendId === $pending->id;
        });
    }

    public function test_it_completes_messages_with_no_pending_sends(): void
    {
        Queue::fake([SendNewsletterEmail::class]);
        User::factory()->create();

        $message = Message::factory()->create([
            'status' => MessageStatus::Sending,
        ]);

        MessageSend::factory()->create([
            'message_id' => $message->id,
            'sent_at' => now(),
            'failed_at' => null,
        ]);

        $this->app->call([new ProcessPendingEmails, 'handle']);

        $message->refresh();
        $this->assertSame(MessageStatus::Sent, $message->status);
        Queue::assertNothingPushed();
    }
}
