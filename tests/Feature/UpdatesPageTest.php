<?php

namespace Tests\Feature;

use App\Filament\Pages\Updates;
use App\Models\User;
use App\Updates\Updater;
use App\Updates\UpdateState;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class UpdatesPageTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        // a git checkout of its own, so the page sees an installation it can update
        $this->tmp = sys_get_temp_dir().'/updates-page-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->tmp.'/app/.git');
        file_put_contents($this->tmp.'/app/VERSION', "1.0.0\n");
        config(['updates.enabled' => true, 'updates.repository' => $this->tmp.'/missing']);
        $this->app->instance(Updater::class, new Updater(new UpdateState($this->tmp.'/state.json'), $this->tmp.'/app'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);

        parent::tearDown();
    }

    public function test_only_administrators_see_the_updates_page(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        Livewire::test(Updates::class)->assertForbidden();

        $this->actingAs(User::factory()->editor()->create());
        Livewire::test(Updates::class)->assertForbidden();

        $this->actingAs(User::factory()->create());
        Livewire::test(Updates::class)
            ->assertSuccessful()
            ->assertSee('1.0.0')
            ->assertSee('Stable releases')
            ->assertSee('The scheduler has not run in the last few minutes');
    }

    public function test_an_administrator_asks_for_an_update_the_scheduler_starts(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(Updates::class)
            ->assertDontSee('The update starts within a minute…')
            ->callAction('forceUpdate')
            ->assertNotified('The update starts within a minute.')
            ->assertSee('The update starts within a minute…')
            ->assertActionDisabled('forceUpdate');

        $state = app(Updater::class)->state();
        $this->assertTrue($state['requested']['force']);
        $this->assertSame($admin->email, $state['requested']['by']);
    }

    public function test_an_administrator_chooses_the_channel_and_the_nightly_update(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Updates::class)
            ->callAction(TestAction::make('betaChannel')->schemaComponent('channel', schema: 'content'))
            ->assertNotified('Newsletter now follows the main branch.')
            ->assertSee('Beta tester mode')
            ->callAction(TestAction::make('toggleAutomatic')->schemaComponent('automatic', schema: 'content'))
            ->assertNotified('Automatic updates are off.');

        $updater = app(Updater::class);
        $this->assertSame('beta', $updater->channel());
        $this->assertFalse($updater->automatic());
        $this->assertSame('unreachable', $updater->state()['waiting_reason']);
    }

    public function test_an_installation_the_updater_cannot_update_says_so(): void
    {
        config(['updates.enabled' => false]);
        $this->actingAs(User::factory()->create());

        Livewire::test(Updates::class)
            ->assertSee('Updates are off on this installation')
            ->assertActionDisabled('forceUpdate')
            ->assertActionDisabled('check');
    }
}
