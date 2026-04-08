<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageApiTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class ManageApiTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ManageApiTokens::class)
            ->assertSuccessful();
    }

    /**
     * Badge columns pass each list item to formatters as a plain string (e.g. "api"), not JSON.
     */
    public function test_normalize_abilities_accepts_single_ability_strings(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(ManageApiTokens::class)->instance();

        $method = new ReflectionMethod(ManageApiTokens::class, 'normalizeAbilities');
        $method->setAccessible(true);

        $this->assertSame(['api'], $method->invoke($component, 'api'));
        $this->assertSame(['mcp'], $method->invoke($component, 'mcp'));
    }

    public function test_token_ability_labels_are_visible_when_token_has_api_and_mcp(): void
    {
        $user = User::factory()->create();
        $user->createToken('ci', ['api', 'mcp']);
        $this->actingAs($user);

        $html = Livewire::test(ManageApiTokens::class)
            ->call('loadTable')
            ->assertSuccessful()
            ->html();

        $this->assertMatchesRegularExpression(
            '/fi-ta-text-has-badges[\s\S]*?fi-badge[\s\S]*?>\s*API\s*</',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/fi-ta-text-has-badges[\s\S]*?fi-badge[\s\S]*?>\s*MCP\s*</',
            $html,
        );

        $this->assertMatchesRegularExpression(
            '/fi-ta-text-has-badges[\s\S]*?fi-color-info[\s\S]*?API[\s\S]*?fi-color-warning[\s\S]*?MCP/',
            $html,
        );
    }
}
