<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\User;
use Filament\Notifications\Notification;

class MessageCompletionService
{
    /**
     * Mark a message as sent when every recipient row is terminal (sent or failed).
     *
     * Returns true when this call performed the transition (avoids duplicate notifications
     * when multiple workers finish the last sends concurrently).
     */
    public function completeIfFinished(Message $message): bool
    {
        $pendingSends = MessageSend::query()
            ->where('message_id', $message->id)
            ->whereNull('sent_at')
            ->whereNull('failed_at')
            ->count();

        if ($pendingSends > 0) {
            return false;
        }

        $sentSends = MessageSend::query()
            ->where('message_id', $message->id)
            ->whereNotNull('sent_at')
            ->count();

        $failedSends = MessageSend::query()
            ->where('message_id', $message->id)
            ->whereNotNull('failed_at')
            ->count();

        if (($sentSends + $failedSends) === 0) {
            return false;
        }

        $updated = Message::query()
            ->whereKey($message->id)
            ->where('status', '!=', MessageStatus::Sent)
            ->update([
                'status' => MessageStatus::Sent,
                'sent_at' => now(),
            ]);

        if ($updated === 0) {
            return false;
        }

        $message->refresh();

        foreach (User::all() as $user) {
            Notification::make()
                ->title(__('Sending completed'))
                ->body(__('Message ":subject" sent to :sent recipients (:failed failed).', [
                    'subject' => $message->subject,
                    'sent' => $sentSends,
                    'failed' => $failedSends,
                ]))
                ->success()
                ->sendToDatabase($user);
        }

        $message->purgeSendsForTestingAudience();

        return true;
    }
}
