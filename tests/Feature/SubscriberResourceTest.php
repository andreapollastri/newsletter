<?php

namespace Tests\Feature;

use App\Enums\SubscriberStatus;
use App\Filament\Resources\Subscribers\Pages\CreateSubscriber;
use App\Filament\Resources\Subscribers\Pages\EditSubscriber;
use App\Filament\Resources\Subscribers\Pages\ListSubscribers;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriberResourceTest extends TestCase
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
        Livewire::test(ListSubscribers::class)
            ->assertSuccessful();
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateSubscriber::class)
            ->assertSuccessful();
    }

    public function test_can_create_subscriber(): void
    {
        $newData = Subscriber::factory()->make();

        Livewire::test(CreateSubscriber::class)
            ->fillForm([
                'email' => $newData->email,
                'name' => $newData->name,
                'status' => SubscriberStatus::Confirmed->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('subscribers', [
            'email' => $newData->email,
            'name' => $newData->name,
        ]);
    }

    public function test_email_is_required(): void
    {
        Livewire::test(CreateSubscriber::class)
            ->fillForm([
                'email' => null,
                'name' => 'Test Name',
                'status' => SubscriberStatus::Pending->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'required']);
    }

    public function test_email_must_be_valid(): void
    {
        Livewire::test(CreateSubscriber::class)
            ->fillForm([
                'email' => 'invalid-email',
                'name' => 'Test Name',
                'status' => SubscriberStatus::Pending->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);
    }

    public function test_email_must_be_unique(): void
    {
        $existingSubscriber = Subscriber::factory()->create();

        Livewire::test(CreateSubscriber::class)
            ->fillForm([
                'email' => $existingSubscriber->email,
                'name' => 'Test Name',
                'status' => SubscriberStatus::Pending->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_can_render_edit_page(): void
    {
        $subscriber = Subscriber::factory()->create();

        Livewire::test(EditSubscriber::class, ['record' => $subscriber->id])
            ->assertSuccessful();
    }

    public function test_can_edit_subscriber(): void
    {
        $subscriber = Subscriber::factory()->create();
        $newData = Subscriber::factory()->make();

        Livewire::test(EditSubscriber::class, ['record' => $subscriber->id])
            ->fillForm([
                'email' => $newData->email,
                'name' => $newData->name,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $subscriber->refresh();
        $this->assertEquals($newData->email, $subscriber->email);
        $this->assertEquals($newData->name, $subscriber->name);
    }

    public function test_can_filter_by_status(): void
    {
        $confirmedSubscriber = Subscriber::factory()->confirmed()->create();
        $pendingSubscriber = Subscriber::factory()->pending()->create();

        Livewire::test(ListSubscribers::class)
            ->assertCanSeeTableRecords([$confirmedSubscriber, $pendingSubscriber])
            ->filterTable('status', SubscriberStatus::Confirmed->value)
            ->assertCanSeeTableRecords([$confirmedSubscriber])
            ->assertCanNotSeeTableRecords([$pendingSubscriber]);
    }

    public function test_can_soft_delete_subscriber(): void
    {
        $subscriber = Subscriber::factory()->create();

        $subscriber->delete();

        $this->assertSoftDeleted($subscriber);
    }
}
