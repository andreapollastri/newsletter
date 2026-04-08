<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserPasswordValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_user_rejects_password_without_mixed_case_numbers_and_symbols(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'role' => UserRole::Editor->value,
                'locale' => 'en',
                'password' => 'password',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }
}
