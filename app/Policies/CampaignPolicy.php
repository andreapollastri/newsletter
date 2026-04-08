<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $campaign->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $campaign->user_id === $user->id;
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $campaign->user_id === $user->id;
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $campaign->user_id === $user->id;
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $campaign->user_id === $user->id;
    }
}
