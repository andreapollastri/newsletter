<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCampaignAndMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_campaigns(): void
    {
        $this->getJson('/api/campaigns')->assertUnauthorized();
    }

    public function test_user_lists_all_campaigns(): void
    {
        $user = User::factory()->create();
        Campaign::factory()->count(2)->create(['user_id' => $user->id]);
        Campaign::factory()->create();

        $token = $user->createToken('test', ['api'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/campaigns');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_authenticated_user_can_view_any_campaign_by_id(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $campaign = Campaign::factory()->create(['user_id' => $owner->id]);

        $token = $other->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->getJson('/api/campaigns/'.$campaign->id)
            ->assertOk()
            ->assertJsonPath('data.id', $campaign->id);
    }

    public function test_user_can_create_update_and_delete_campaign(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $create = $this->withToken($token)->postJson('/api/campaigns', [
            'name' => 'Spring sale',
            'description' => 'Promo',
        ]);

        $create->assertCreated();
        $id = $create->json('data.id');
        $this->assertNotEmpty($create->json('data.slug'));

        $this->withToken($token)->putJson('/api/campaigns/'.$id, [
            'name' => 'Spring sale updated',
        ])->assertOk()->assertJsonPath('data.name', 'Spring sale updated');

        $this->withToken($token)->deleteJson('/api/campaigns/'.$id)
            ->assertNoContent();

        $this->assertSoftDeleted('campaigns', ['id' => $id]);
    }

    public function test_user_can_crud_messages_in_own_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);
        $template = Template::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $store = $this->withToken($token)->postJson('/api/campaigns/'.$campaign->id.'/messages', [
            'template_id' => $template->id,
            'subject' => 'Hello',
            'html_content' => '<p>Body</p>',
            'status' => MessageStatus::Draft->value,
        ]);

        $store->assertCreated();
        $messageId = $store->json('data.id');

        $this->withToken($token)->getJson('/api/campaigns/'.$campaign->id.'/messages/'.$messageId)
            ->assertOk()
            ->assertJsonPath('data.subject', 'Hello');

        $this->withToken($token)->putJson('/api/campaigns/'.$campaign->id.'/messages/'.$messageId, [
            'subject' => 'Updated',
        ])->assertOk()->assertJsonPath('data.subject', 'Updated');

        $this->withToken($token)->deleteJson('/api/campaigns/'.$campaign->id.'/messages/'.$messageId)
            ->assertNoContent();

        $this->assertDatabaseMissing('messages', ['id' => $messageId]);
    }

    public function test_message_from_other_campaign_returns_not_found(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);
        $otherCampaign = Campaign::factory()->create(['user_id' => $user->id]);
        $template = Template::factory()->create();
        $message = Message::factory()->for($otherCampaign)->create([
            'template_id' => $template->id,
        ]);

        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->getJson('/api/campaigns/'.$campaign->id.'/messages/'.$message->id)
            ->assertNotFound();
    }

    public function test_cannot_update_sent_message_via_api(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);
        $message = Message::factory()->for($campaign)->sent()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->putJson('/api/campaigns/'.$campaign->id.'/messages/'.$message->id, [
            'subject' => 'Updated',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.status.0', __('Sent or sending messages cannot be modified via API.'));

        $this->withToken($token)->putJson('/api/campaigns/'.$campaign->id.'/messages/'.$message->id, [])
            ->assertForbidden();
    }

    public function test_cannot_delete_sent_message_via_api(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);
        $message = Message::factory()->for($campaign)->sent()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->deleteJson('/api/campaigns/'.$campaign->id.'/messages/'.$message->id)
            ->assertForbidden();
    }
}
