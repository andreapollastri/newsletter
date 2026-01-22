<?php

namespace App\Http\Controllers;

use App\Enums\SubscriberStatus;
use App\Mail\SubscriptionConfirmation;
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
     * Unsubscribe.
     */
    public function unsubscribe(Subscriber $subscriber): View
    {
        if ($subscriber->status !== SubscriberStatus::Unsubscribed) {
            $subscriber->update([
                'status' => SubscriberStatus::Unsubscribed,
                'unsubscribed_at' => now(),
            ]);
        }

        return view('subscribe.unsubscribed');
    }
}
