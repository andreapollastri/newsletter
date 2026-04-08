<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function create(User $user): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $user->canAccessManagementFeatures();
    }
}
