<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\Pages\CreateMessage;
use App\Filament\Resources\Messages\Pages\EditMessage;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Tag;
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

    public function test_can_duplicate_draft_message(): void
    {
        $campaign = Campaign::factory()->create(['user_id' => $this->user->id]);
        $message = Message::factory()->create([
            'campaign_id' => $campaign->id,
            'subject' => 'Test Subject',
            'html_content' => '<p>Test content</p>',
            'status' => MessageStatus::Draft,
        ]);

        Livewire::test(ListMessages::class)
            ->callTableAction('duplicate', $message);

        $this->assertDatabaseHas('messages', [
            'campaign_id' => $campaign->id,
            'subject' => 'Test Subject ('.__('Copy').')',
            'html_content' => '<p>Test content</p>',
            'status' => MessageStatus::Draft->value,
        ]);

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_can_duplicate_sent_message(): void
    {
        $campaign = Campaign::factory()->create(['user_id' => $this->user->id]);
        $message = Message::factory()->sent()->create([
            'campaign_id' => $campaign->id,
            'subject' => 'Sent Message',
            'html_content' => '<p>Sent content</p>',
            'status' => MessageStatus::Sent,
        ]);

        Livewire::test(ListMessages::class)
            ->callTableAction('duplicate', $message);

        $this->assertDatabaseHas('messages', [
            'campaign_id' => $campaign->id,
            'subject' => 'Sent Message ('.__('Copy').')',
            'html_content' => '<p>Sent content</p>',
            'status' => MessageStatus::Draft->value,
            'sent_at' => null,
        ]);

        $this->assertDatabaseCount('messages', 2);
    }

    public function test_duplicate_resets_scheduled_and_sent_dates(): void
    {
        $message = Message::factory()->create([
            'scheduled_at' => now()->addDay(),
            'sent_at' => now(),
        ]);

        Livewire::test(ListMessages::class)
            ->callTableAction('duplicate', $message);

        $duplicate = Message::where('subject', $message->subject.' ('.__('Copy').')')->first();

        $this->assertNull($duplicate->scheduled_at);
        $this->assertNull($duplicate->sent_at);
        $this->assertEquals(MessageStatus::Draft, $duplicate->status);
    }

    public function test_duplicate_copies_tag_relationships(): void
    {
        $message = Message::factory()->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $message->tags()->attach([$tag1->id, $tag2->id]);

        Livewire::test(ListMessages::class)
            ->callTableAction('duplicate', $message);

        $duplicate = Message::where('subject', $message->subject.' ('.__('Copy').')')->first();

        $this->assertCount(2, $duplicate->tags);
        $this->assertTrue($duplicate->tags->contains($tag1));
        $this->assertTrue($duplicate->tags->contains($tag2));
    }

    public function test_can_delete_draft_message(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Draft]);

        Livewire::test(ListMessages::class)
            ->callTableAction('delete', $message);

        $this->assertDatabaseMissing('messages', [
            'id' => $message->id,
        ]);
    }

    public function test_can_delete_ready_message(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Ready]);

        Livewire::test(ListMessages::class)
            ->callTableAction('delete', $message);

        $this->assertDatabaseMissing('messages', [
            'id' => $message->id,
        ]);
    }

    public function test_cannot_see_delete_action_for_sending_message(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Sending]);

        Livewire::test(ListMessages::class)
            ->assertTableActionHidden('delete', $message);
    }

    public function test_cannot_see_delete_action_for_sent_message(): void
    {
        $message = Message::factory()->sent()->create();

        Livewire::test(ListMessages::class)
            ->assertTableActionHidden('delete', $message);
    }

    public function test_deleting_message_cascades_to_message_sends(): void
    {
        $message = Message::factory()->create(['status' => MessageStatus::Draft]);
        $messageSend1 = MessageSend::factory()->create(['message_id' => $message->id]);
        $messageSend2 = MessageSend::factory()->create(['message_id' => $message->id]);

        $this->assertDatabaseHas('message_sends', ['id' => $messageSend1->id]);
        $this->assertDatabaseHas('message_sends', ['id' => $messageSend2->id]);

        Livewire::test(ListMessages::class)
            ->callTableAction('delete', $message);

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('message_sends', ['id' => $messageSend1->id]);
        $this->assertDatabaseMissing('message_sends', ['id' => $messageSend2->id]);
    }
}
