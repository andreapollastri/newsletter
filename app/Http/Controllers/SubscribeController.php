<?php

namespace App\Http\Controllers;

use App\Enums\SubscriberStatus;
use App\Mail\SubscriptionConfirmation;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubscribeController extends Controller
{
    /**
     * Show subscription form.
     */
    public function showForm(): View
    {
        return view('subscribe.form');
    }

    /**
     * Handle subscription request.
     */
    public function subscribe(Request $request): View
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if already subscribed
        $existing = Subscriber::where('email', $request->email)->first();

        if ($existing) {
            if ($existing->status === SubscriberStatus::Confirmed) {
                return view('subscribe.already-subscribed');
            }

            if ($existing->status === SubscriberStatus::Pending) {
                // Resend confirmation
                Mail::to($existing->email)->send(new SubscriptionConfirmation($existing));

                return view('subscribe.pending');
            }

            // If unsubscribed or bounced, allow re-subscription
            $existing->update([
                'name' => $request->name ?? $existing->name,
                'status' => SubscriberStatus::Pending,
                'confirmation_token' => Str::random(64),
                'unsubscribed_at' => null,
            ]);

            Mail::to($existing->email)->send(new SubscriptionConfirmation($existing));

            return view('subscribe.pending');
        }

        // Create new subscriber
        $subscriber = Subscriber::create([
            'email' => $request->email,
            'name' => $request->name,
            'status' => SubscriberStatus::Pending,
            'confirmation_token' => Str::random(64),
        ]);

        // Send confirmation email
        Mail::to($subscriber->email)->send(new SubscriptionConfirmation($subscriber));

        return view('subscribe.pending');
    }

    /**
     * Confirm subscription.
     */
    public function confirm(string $token): View
    {
        $subscriber = Subscriber::where('confirmation_token', $token)
            ->where('status', SubscriberStatus::Pending)
            ->first();

        if (! $subscriber) {
            return view('subscribe.invalid-token');
        }

        $subscriber->update([
            'status' => SubscriberStatus::Confirmed,
            'confirmed_at' => now(),
            'confirmation_token' => null,
        ]);

        return view('subscribe.confirmed');
    }

    /**
     * Show unsubscribe confirmation page.
     */
    public function unsubscribe(Request $request, Subscriber $subscriber): View
    {
        // Store message_send in session to use in confirmUnsubscribe
        if ($request->has('message_send')) {
            $request->session()->put('unsubscribe_message_send', $request->query('message_send'));
        }

        return view('subscribe.unsubscribe-confirm', compact('subscriber'));
    }

    /**
     * Handle RFC 8058 one-click unsubscribe (POST to the List-Unsubscribe URL).
     */
    public function oneClickUnsubscribe(Request $request, Subscriber $subscriber): Response
    {
        $this->markUnsubscribed($subscriber, $this->messageIdForSend($request->query('message_send')));

        return response()->noContent();
    }

    /**
     * Unsubscribe endpoint for test sends that always returns success.
     */
    public function testUnsubscribe(): Response
    {
        return response()->noContent();
    }

    /**
     * Confirm and process unsubscribe.
     */
    public function confirmUnsubscribe(Request $request, Subscriber $subscriber): View
    {
        // The message_send comes from the email link (query) or from the confirmation page (session)
        $sessionMessageSendId = $request->session()->pull('unsubscribe_message_send');
        $messageSendId = $request->query('message_send') ?? $sessionMessageSendId;

        $this->markUnsubscribed($subscriber, $this->messageIdForSend($messageSendId));

        return view('subscribe.unsubscribed');
    }

    /**
     * Message that a send belongs to, for unsubscribe attribution. Ignores malformed ids from links.
     */
    protected function messageIdForSend(mixed $messageSendId): ?string
    {
        if (! is_string($messageSendId) || ! Str::isUuid($messageSendId)) {
            return null;
        }

        return MessageSend::query()->whereKey($messageSendId)->value('message_id');
    }

    /**
     * Mark the subscriber as unsubscribed.
     */
    protected function markUnsubscribed(Subscriber $subscriber, ?string $messageId): void
    {
        if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
            $subscriber->update([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
                'unsubscribed_from_message_id' => $messageId,
            ]);
        }
    }
}
