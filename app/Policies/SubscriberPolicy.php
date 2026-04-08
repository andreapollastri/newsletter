<?php

namespace App\Policies;

use App\Models\Subscriber;
use App\Models\User;

class SubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subscriber $subscriber): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Subscriber $subscriber): bool
    {
        return true;
    }

    public function delete(User $user, Subscriber $subscriber): bool
    {
        return true;
    }

    public function restore(User $user, Subscriber $subscriber): bool
    {
        return true;
    }

    public function forceDelete(User $user, Subscriber $subscriber): bool
    {
        return true;
    }
}
