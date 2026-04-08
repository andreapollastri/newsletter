<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_panel_sets_app_locale_from_authenticated_user(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user);
        $this->get('/');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_filament_panel_uses_italian_when_user_prefers_it(): void
    {
        $user = User::factory()->create(['locale' => 'it']);

        $this->actingAs($user);
        $this->get('/');

        $this->assertSame('it', app()->getLocale());
    }

    public function test_filament_panel_sets_german_locale(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user);
        $this->get('/');

        $this->assertSame('de', app()->getLocale());
    }

    public function test_user_preferred_locale_returns_valid_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->assertSame('en', $user->preferredLocale());
    }

    public function test_user_preferred_locale_falls_back_for_invalid_value(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['locale' => 'xx']);
        $user->refresh();

        $this->assertSame(UserLocale::default(), $user->preferredLocale());
    }

    public function test_login_page_uses_accept_language_when_supported(): void
    {
        $this->withHeader('Accept-Language', 'pt-BR,pt;q=0.9,en;q=0.8')
            ->get('/login');

        $this->assertSame('pt', app()->getLocale());
    }

    public function test_login_page_falls_back_to_english_for_unsupported_language(): void
    {
        $this->withHeader('Accept-Language', 'zh-CN,zh;q=0.9')
            ->get('/login');

        $this->assertSame('en', app()->getLocale());
    }
}
