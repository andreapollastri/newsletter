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
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $user->canAccessManagementFeatures();
    }
}
