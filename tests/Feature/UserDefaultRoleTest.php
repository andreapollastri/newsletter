<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDefaultRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_user_without_a_role_becomes_administrator(): void
    {
        $first = User::create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret']);
        $second = User::create(['name' => 'Writer', 'email' => 'writer@example.com', 'password' => 'secret']);

        $this->assertSame(UserRole::Administrator, $first->fresh()->role);
        $this->assertSame(UserRole::Editor, $second->fresh()->role);
    }

    public function test_explicit_role_is_kept_for_the_first_user(): void
    {
        $user = User::create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => 'secret',
            'role' => UserRole::Manager,
        ]);

        $this->assertSame(UserRole::Manager, $user->fresh()->role);
    }

    public function test_make_filament_user_on_a_fresh_install_creates_an_administrator(): void
    {
        $this->artisan('make:filament-user', [
            '--name' => 'Owner',
            '--email' => 'owner@example.com',
            '--password' => 'secret-Password-123',
        ])->assertSuccessful();

        $this->assertSame(UserRole::Administrator, User::where('email', 'owner@example.com')->first()->role);
    }
}
