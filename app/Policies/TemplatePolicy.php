<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;

class TemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function view(User $user, Template $template): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function create(User $user): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function update(User $user, Template $template): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function delete(User $user, Template $template): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function restore(User $user, Template $template): bool
    {
        return $user->canAccessManagementFeatures();
    }

    public function forceDelete(User $user, Template $template): bool
    {
        return $user->canAccessManagementFeatures();
    }
}
