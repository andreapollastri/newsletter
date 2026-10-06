<?php

namespace Tests\Feature;

use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Filament\Resources\Templates\Pages\ListTemplates;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailPreviewActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['name' => 'Ada Lovelace']));
    }

    public function test_message_preview_renders_template_and_body_in_a_sandboxed_frame(): void
    {
        $template = Template::factory()->create(['html_content' => '<div class="frame">Hi {{name}} {{body}}</div>']);
        $message = Message::factory()->create([
            'template_id' => $template->id,
            'html_content' => '<p>Preview body</p>',
        ]);

        Livewire::test(ListMessages::class)
            ->mountAction(TestAction::make('preview')->table($message))
            ->assertMountedActionModalSeeHtml('sandbox=""')
            ->assertMountedActionModalSee('<div class="frame">Hi Ada Lovelace <p>Preview body</p></div>');
    }

    public function test_message_preview_is_available_on_the_view_page(): void
    {
        $message = Message::factory()->sent()->create(['html_content' => '<p>Archived body</p>']);

        Livewire::test(ViewMessage::class, ['record' => $message->getRouteKey()])
            ->mountAction('preview')
            ->assertMountedActionModalSee('<p>Archived body</p>');
    }

    public function test_template_preview_uses_sample_content(): void
    {
        $template = Template::factory()->create(['html_content' => '<section>{{body}}</section>']);

        Livewire::test(ListTemplates::class)
            ->mountAction(TestAction::make('preview')->table($template))
            ->assertMountedActionModalSee('<section><h2>Your headline goes here</h2>');
    }
}
