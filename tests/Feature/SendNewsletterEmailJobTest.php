<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Jobs\SendNewsletterEmail;
use App\Mail\NewsletterMail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendNewsletterEmailJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sends_email(): void
    {
        Mail::fake();

        $subscriber = Subscriber::factory()->confirmed()->create();
        $message = Message::factory()->ready()->create([
            'subject' => 'Test Subject',
            'html_content' => '<p>Test content</p>',
        ]);
        $messageSend = MessageSend::factory()->create([
            'message_id' => $message->id,
            'subscriber_id' => $subscriber->id,
        ]);

        SendNewsletterEmail::dispatch($messageSend->id);

        Mail::assertSent(NewsletterMail::class, function ($mail) use ($subscriber) {
            return $mail->hasTo($subscriber->email);
        });
    }

    public function test_job_marks_as_sent_on_success(): void
    {
        Mail::fake();

        $messageSend = MessageSend::factory()->create();

        SendNewsletterEmail::dispatch($messageSend->id);

        $messageSend->refresh();
        $this->assertNotNull($messageSend->sent_at);
    }

    public function test_job_replaces_placeholders(): void
    {
        Mail::fake();

        $subscriber = Subscriber::factory()->confirmed()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $message = Message::factory()->ready()->create([
            'subject' => 'Hello {{name}}',
            'html_content' => '<p>Email: {{email}}</p>',
        ]);
        $messageSend = MessageSend::factory()->create([
            'message_id' => $message->id,
            'subscriber_id' => $subscriber->id,
        ]);

        SendNewsletterEmail::dispatch($messageSend->id);

        Mail::assertSent(NewsletterMail::class, function ($mail) {
            return $mail->emailSubject === 'Hello John Doe'
                && str_contains($mail->htmlContent, 'Email: john@example.com');
        });
    }

    public function test_job_adds_tracking_pixel(): void
    {
        Mail::fake();
        config(['newsletter.tracking.enabled' => true]);

        $messageSend = MessageSend::factory()->create();

        SendNewsletterEmail::dispatch($messageSend->id);

        Mail::assertSent(NewsletterMail::class, function ($mail) use ($messageSend) {
            return str_contains($mail->htmlContent, route('tracking.open', $messageSend->id));
        });
    }

    public function test_job_updates_message_status_when_all_sends_complete(): void
    {
        Mail::fake();

        $message = Message::factory()->create([
            'status' => MessageStatus::Sending,
        ]);
        $messageSend = MessageSend::factory()->create([
            'message_id' => $message->id,
        ]);

        SendNewsletterEmail::dispatch($messageSend->id);

        $message->refresh();
        $this->assertEquals(MessageStatus::Sent, $message->status);
    }
}
