<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\User;
use Filament\Notifications\Notification;

class MessageDispatchService
{
    /**
     * Subscribers loaded per query while building the send queue.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Move a message to "sending" and queue one SendNewsletterEmail job per target subscriber.
     *
     * Subscribers that already have a send row for this message are skipped, so a repeated call never
     * emails anyone twice. Recipients are streamed in chunks to keep memory flat on large lists.
     * When the audience is empty the message is left untouched and 0 is returned.
     *
     * @return int Number of newly queued sends.
     */
    public function dispatch(Message $message): int
    {
        $alreadyQueued = MessageSend::query()
            ->where('message_id', $message->id)
            ->pluck('subscriber_id')
            ->flip();

        $recipients = $message->targetSubscribers()->select('subscribers.id');

        if ($alreadyQueued->isEmpty() && ! $recipients->clone()->exists()) {
            return 0;
        }

        $message->update(['status' => MessageStatus::Sending]);

        $queued = 0;

        $recipients
            ->lazyById(self::CHUNK_SIZE, 'subscribers.id', 'id')
            ->reject(fn (Subscriber $subscriber): bool => $alreadyQueued->has($subscriber->id))
            ->each(function (Subscriber $subscriber) use ($message, &$queued): void {
                $messageSend = MessageSend::create([
                    'message_id' => $message->id,
                    'subscriber_id' => $subscriber->id,
                ]);

                SendNewsletterEmail::dispatch($messageSend->id);
                $queued++;
            });

        if ($queued > 0) {
            $this->notifyUsers(
                Notification::make()
                    ->title(__('Sending started'))
                    ->body(__('Message ":subject" is being sent to :count recipients.', [
                        'subject' => $message->subject,
                        'count' => $queued,
                    ]))
                    ->success(),
            );
        }

        return $queued;
    }

    /**
     * Move a due scheduled message without recipients back to draft so it does not sit in "ready" forever.
     */
    public function returnToDraftWithoutRecipients(Message $message): void
    {
        $message->update(['status' => MessageStatus::Draft]);

        $this->notifyUsers(
            Notification::make()
                ->title(__('No recipients'))
                ->body(__('Scheduled message ":subject" matched no confirmed subscribers and was moved back to draft.', [
                    'subject' => $message->subject,
                ]))
                ->warning(),
        );
    }

    private function notifyUsers(Notification $notification): void
    {
        User::query()->each(fn (User $user) => $notification->sendToDatabase($user));
    }
}
