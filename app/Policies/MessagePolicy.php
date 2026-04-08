<?php

namespace App\Policies;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Message $message): bool
    {
        if ($user->isEditor()) {
            return ! in_array($message->status, [MessageStatus::Sent, MessageStatus::Sending], true);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Message $message): bool
    {
        if ($user->isEditor()) {
            return $message->status === MessageStatus::Draft;
        }

        return true;
    }

    public function delete(User $user, Message $message): bool
    {
        if ($user->isEditor()) {
            return $message->status === MessageStatus::Draft;
        }

        return true;
    }

    public function restore(User $user, Message $message): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function forceDelete(User $user, Message $message): bool
    {
        return $user->canAccessManagementFeatures();
    }
}
