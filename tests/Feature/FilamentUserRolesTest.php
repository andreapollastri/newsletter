<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ManageApiTokens;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentUserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_is_redirected_from_dashboard_to_campaigns(): void
    {
        $user = User::factory()->editor()->create();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertRedirect(CampaignResource::getUrl());
    }

    public function test_editor_cannot_open_user_management(): void
    {
        $user = User::factory()->editor()->create();
        $this->actingAs($user);

        Livewire::test(ListUsers::class)->assertForbidden();
    }

    public function test_manager_cannot_open_user_management(): void
    {
        $user = User::factory()->manager()->create();
        $this->actingAs($user);

        Livewire::test(ListUsers::class)->assertForbidden();
    }

    public function test_administrator_can_open_user_management(): void
    {
        $user = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($user);

        Livewire::test(ListUsers::class)->assertSuccessful();
    }

    public function test_manager_cannot_access_api_tokens_page(): void
    {
        $user = User::factory()->manager()->create();
        $this->actingAs($user);

        $this->get(ManageApiTokens::getUrl())->assertForbidden();
    }
}
