<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Jobs\SendNewsletterEmail;
use App\Mail\NewsletterMail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\Tag;
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

        // Execute job directly instead of dispatching
        $job = new SendNewsletterEmail($messageSend->id);
        $this->app->call([$job, 'handle']);

        Mail::assertSent(NewsletterMail::class, function ($mail) use ($subscriber) {
            return $mail->hasTo($subscriber->email);
        });
    }

    public function test_job_marks_as_sent_on_success(): void
    {
        Mail::fake();

        $messageSend = MessageSend::factory()->create();

        // Execute job directly
        $job = new SendNewsletterEmail($messageSend->id);
        $this->app->call([$job, 'handle']);

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

        // Execute job directly
        $job = new SendNewsletterEmail($messageSend->id);
        $this->app->call([$job, 'handle']);

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

        // Execute job directly
        $job = new SendNewsletterEmail($messageSend->id);
        $this->app->call([$job, 'handle']);

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

        // Execute job directly
        $job = new SendNewsletterEmail($messageSend->id);
        $this->app->call([$job, 'handle']);

        $message->refresh();
        $this->assertEquals(MessageStatus::Sent, $message->status);
    }

    public function test_job_purges_message_sends_after_completion_for_testing_audience_only(): void
    {
        Mail::fake();

        $tag = Tag::factory()->testing()->create();
        $message = Message::factory()->create([
            'status' => MessageStatus::Sending,
        ]);
        $message->tags()->attach($tag->id);

        $messageSend = MessageSend::factory()->create([
            'message_id' => $message->id,
        ]);

        $job = new SendNewsletterEmail($messageSend->id);
        $this->app->call([$job, 'handle']);

        $message->refresh();
        $this->assertEquals(MessageStatus::Sent, $message->status);
        $this->assertEmpty(MessageSend::where('message_id', $message->id)->get());
    }

    public function test_job_wraps_links_with_signed_tracking_urls_that_keep_query_strings(): void
    {
        Mail::fake();
        config(['newsletter.tracking.enabled' => true]);

        $message = Message::factory()->ready()->create([
            'html_content' => '<p><a href="https://example.com/page?a=1&amp;b=2">Read more</a></p>',
        ]);
        $messageSend = MessageSend::factory()->create(['message_id' => $message->id, 'clicks_count' => 0]);

        $this->app->call([new SendNewsletterEmail($messageSend->id), 'handle']);

        $html = Mail::sent(NewsletterMail::class)->first()->htmlContent;
        $this->assertSame(1, preg_match('#href="([^"]*/track/click/[^"]+)"#', $html, $matches));

        $trackingUrl = html_entity_decode($matches[1]);
        $this->assertStringContainsString('signature=', $trackingUrl);

        $response = $this->get($trackingUrl);

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://example.com/page?a=1&b=2&utm_source=nl&utm_medium=newsletter',
            $response->headers->get('Location'),
        );
        $this->assertSame(1, $messageSend->fresh()->clicks_count);
    }

    public function test_job_leaves_non_http_links_untouched(): void
    {
        Mail::fake();
        config(['newsletter.tracking.enabled' => true]);

        $message = Message::factory()->ready()->create([
            'html_content' => '<a href="mailto:hello@example.com">Write</a> <a href="tel:+3900000000">Call</a> <a href="#top">Top</a>',
        ]);
        $messageSend = MessageSend::factory()->create(['message_id' => $message->id]);

        $this->app->call([new SendNewsletterEmail($messageSend->id), 'handle']);

        $html = Mail::sent(NewsletterMail::class)->first()->htmlContent;
        $this->assertStringContainsString('href="mailto:hello@example.com"', $html);
        $this->assertStringContainsString('href="tel:+3900000000"', $html);
        $this->assertStringContainsString('href="#top"', $html);
        $this->assertStringNotContainsString('/track/click/', $html);
    }

    public function test_job_adds_one_click_list_unsubscribe_headers(): void
    {
        Mail::fake();

        $messageSend = MessageSend::factory()->create();

        $this->app->call([new SendNewsletterEmail($messageSend->id), 'handle']);

        Mail::assertSent(NewsletterMail::class, function (NewsletterMail $mail) use ($messageSend): bool {
            $headers = $mail->headers()->text;
            $expectedUrl = route('unsubscribe', $messageSend->subscriber_id).'?message_send='.$messageSend->id;

            return $headers['List-Unsubscribe'] === '<'.$expectedUrl.'>'
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });
    }
}
