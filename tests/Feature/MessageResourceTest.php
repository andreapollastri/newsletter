<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\Pages\CreateMessage;
use App\Filament\Resources\Messages\Pages\EditMessage;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MessageResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_render_list_page(): void
    {
        Livewire::test(ListMessages::class)
            ->assertSuccessful();
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateMessage::class)
            ->assertSuccessful();
    }

    public function test_can_create_message(): void
    {
        $campaign = Campaign::factory()->create(['user_id' => $this->user->id]);

        Livewire::test(CreateMessage::class)
            ->fillForm([
                'campaign_id' => $campaign->id,
                'subject' => 'Test Subject',
                'html_content' => '<p>Test content</p>',
                'status' => MessageStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('messages', [
            'campaign_id' => $campaign->id,
            'subject' => 'Test Subject',
        ]);
    }

    public function test_campaign_is_required(): void
    {
        Livewire::test(CreateMessage::class)
            ->fillForm([
                'campaign_id' => null,
                'subject' => 'Test Subject',
                'html_content' => '<p>Test content</p>',
                'status' => MessageStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['campaign_id' => 'required']);
    }

    public function test_subject_is_required(): void
    {
        $campaign = Campaign::factory()->create(['user_id' => $this->user->id]);

        Livewire::test(CreateMessage::class)
            ->fillForm([
                'campaign_id' => $campaign->id,
                'subject' => null,
                'html_content' => '<p>Test content</p>',
                'status' => MessageStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['subject' => 'required']);
    }

    public function test_html_content_is_required(): void
    {
        $campaign = Campaign::factory()->create(['user_id' => $this->user->id]);

        Livewire::test(CreateMessage::class)
            ->fillForm([
                'campaign_id' => $campaign->id,
                'subject' => 'Test Subject',
                'html_content' => '',
                'status' => MessageStatus::Draft->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['html_content']);
    }

    public function test_can_render_edit_page(): void
    {
        $message = Message::factory()->create();

        Livewire::test(EditMessage::class, ['record' => $message->id])
            ->assertSuccessful();
    }

    public function test_can_edit_message(): void
    {
        $message = Message::factory()->create();

        Livewire::test(EditMessage::class, ['record' => $message->id])
            ->fillForm([
                'subject' => 'Updated Subject',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $message->refresh();
        $this->assertEquals('Updated Subject', $message->subject);
    }

    public function test_can_filter_by_status(): void
    {
        $draftMessage = Message::factory()->create(['status' => MessageStatus::Draft]);
        $sentMessage = Message::factory()->sent()->create();

        Livewire::test(ListMessages::class)
            ->assertCanSeeTableRecords([$draftMessage, $sentMessage])
            ->filterTable('status', MessageStatus::Draft->value)
            ->assertCanSeeTableRecords([$draftMessage])
            ->assertCanNotSeeTableRecords([$sentMessage]);
    }
}
