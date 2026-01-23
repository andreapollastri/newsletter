<?php

namespace App\Http\Controllers;

use App\Enums\SubscriberStatus;
use App\Mail\SubscriptionConfirmation;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Illuminate\Http\Request;
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
     * Confirm and process unsubscribe.
     */
    public function confirmUnsubscribe(Request $request, Subscriber $subscriber): View
    {
        $messageId = null;

        // Get message_id from message_send if provided (from query or session)
        $messageSendId = $request->query('message_send') ?? $request->session()->get('unsubscribe_message_send');
        if ($messageSendId) {
            $messageSend = MessageSend::find($messageSendId);
            if ($messageSend) {
                $messageId = $messageSend->message_id;
            }
            // Clear session
            $request->session()->forget('unsubscribe_message_send');
        }

        if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
            $subscriber->update([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
                'unsubscribed_from_message_id' => $messageId,
            ]);
        }

        return view('subscribe.unsubscribed');
    }
}
