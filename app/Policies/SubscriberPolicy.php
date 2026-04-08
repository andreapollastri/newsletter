<?php

namespace App\Policies;

use App\Models\Subscriber;
use App\Models\User;

class SubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function view(User $user, Subscriber $subscriber): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function create(User $user): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function update(User $user, Subscriber $subscriber): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function delete(User $user, Subscriber $subscriber): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function restore(User $user, Subscriber $subscriber): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function forceDelete(User $user, Subscriber $subscriber): bool
    {
        return $user->canAccessManagementFeatures();
    }
}
