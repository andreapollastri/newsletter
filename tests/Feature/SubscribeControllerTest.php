<?php

namespace Tests\Feature;

use App\Enums\SubscriberStatus;
use App\Mail\SubscriptionConfirmation;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_subscription_form(): void
    {
        $this->get(route('subscribe.form'))
            ->assertSuccessful()
            ->assertViewIs('subscribe.form');
    }

    public function test_subscription_creates_pending_subscriber(): void
    {
        Mail::fake();

        $this->post(route('subscribe.store'), [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ])->assertViewIs('subscribe.pending');

        $this->assertDatabaseHas('subscribers', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'status' => SubscriberStatus::Pending->value,
        ]);
    }

    public function test_subscription_sends_confirmation_email(): void
    {
        Mail::fake();

        $this->post(route('subscribe.store'), [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        Mail::assertSent(SubscriptionConfirmation::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_confirmation_sets_subscriber_status_to_confirmed(): void
    {
        $subscriber = Subscriber::factory()->pending()->create();

        $this->get(route('subscribe.confirm', $subscriber->confirmation_token))
            ->assertViewIs('subscribe.confirmed');

        $subscriber->refresh();
        $this->assertEquals(SubscriberStatus::Confirmed, $subscriber->status);
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertNull($subscriber->confirmation_token);
    }

    public function test_invalid_token_shows_error(): void
    {
        $this->get(route('subscribe.confirm', 'invalid-token'))
            ->assertViewIs('subscribe.invalid-token');
    }

    public function test_unsubscribe_shows_confirmation_page(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create();

        $this->get(route('unsubscribe', $subscriber))
            ->assertViewIs('subscribe.unsubscribe-confirm')
            ->assertViewHas('subscriber');

        // Subscriber should NOT be unsubscribed yet
        $subscriber->refresh();
        $this->assertEquals(SubscriberStatus::Confirmed, $subscriber->status);
        $this->assertNull($subscriber->unsubscribed_at);
    }

    public function test_confirm_unsubscribe_sets_subscriber_status_to_unsubscribed(): void
    {
        $subscriber = Subscriber::factory()->confirmed()->create();

        $this->post(route('unsubscribe.confirm', $subscriber))
            ->assertViewIs('subscribe.unsubscribed');

        $subscriber->refresh();
        $this->assertEquals(SubscriberStatus::Unsubscribed, $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
    }

    public function test_already_subscribed_shows_appropriate_message(): void
    {
        Mail::fake();

        $subscriber = Subscriber::factory()->confirmed()->create();

        $this->post(route('subscribe.store'), [
            'email' => $subscriber->email,
            'name' => 'Test User',
        ])->assertViewIs('subscribe.already-subscribed');
    }

    public function test_subscription_requires_valid_email(): void
    {
        $this->post(route('subscribe.store'), [
            'email' => 'invalid-email',
            'name' => 'Test User',
        ])->assertSessionHasErrors('email');
    }

    public function test_subscription_requires_email(): void
    {
        $this->post(route('subscribe.store'), [
            'name' => 'Test User',
        ])->assertSessionHasErrors('email');
    }
}
