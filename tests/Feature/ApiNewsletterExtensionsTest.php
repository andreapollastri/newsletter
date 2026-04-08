<?php

namespace Tests\Feature;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use App\Models\Campaign;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use App\Sanctum\TokenAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiNewsletterExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_subscribers_templates_and_reports_require_api_ability(): void
    {
        $user = User::factory()->create();
        $mcpOnly = $user->createToken('mcp', [TokenAbility::Mcp])->plainTextToken;

        $this->withToken($mcpOnly)->getJson('/api/tags')->assertForbidden();
        $this->withToken($mcpOnly)->getJson('/api/subscribers')->assertForbidden();
        $this->withToken($mcpOnly)->getJson('/api/templates')->assertForbidden();
        $this->withToken($mcpOnly)->getJson('/api/reports/newsletter?start_date=2026-01-01&end_date=2026-01-31')->assertForbidden();
    }

    public function test_wildcard_token_can_use_rest_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('all', ['*'])->plainTextToken;

        Tag::factory()->create(['name' => 'alpha']);

        $this->withToken($token)->getJson('/api/tags')->assertOk();
    }

    public function test_mcp_route_requires_mcp_ability(): void
    {
        $user = User::factory()->create();
        $apiOnly = $user->createToken('api', [TokenAbility::Api])->plainTextToken;

        $this->withToken($apiOnly)->postJson('/mcp/newsletter', [])->assertForbidden();
    }

    public function test_user_can_list_and_create_tags_with_api_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api', [TokenAbility::Api])->plainTextToken;

        $this->withToken($token)->postJson('/api/tags', [
            'name' => 'vip',
            'is_testing' => false,
        ])->assertCreated()->assertJsonPath('data.name', 'vip');

        $this->withToken($token)->getJson('/api/tags')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_newsletter_report_respects_campaign_scope(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create(['user_id' => $user->id]);
        $template = Template::factory()->create();

        $message = $campaign->messages()->create([
            'template_id' => $template->id,
            'subject' => 'Hi',
            'html_content' => '<p>x</p>',
            'status' => MessageStatus::Sent,
            'sent_at' => now(),
        ]);

        $subscriber = Subscriber::factory()->create();
        MessageSend::factory()->create([
            'message_id' => $message->id,
            'subscriber_id' => $subscriber->id,
            'sent_at' => now(),
            'opens_count' => 2,
            'clicks_count' => 1,
        ]);

        $token = $user->createToken('api', [TokenAbility::Api])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/reports/newsletter?'.http_build_query([
            'campaign_id' => $campaign->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->json('summary.sends'));
        $this->assertSame(2, $response->json('summary.opens'));
    }

    public function test_subscriber_tag_sync_via_api(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $token = $user->createToken('api', [TokenAbility::Api])->plainTextToken;

        $create = $this->withToken($token)->postJson('/api/subscribers', [
            'email' => 'reader@example.com',
            'name' => 'Reader',
            'status' => SubscriberStatus::Confirmed->value,
            'tag_ids' => [$tag->id],
        ]);

        $create->assertCreated();
        $id = $create->json('data.id');

        $this->withToken($token)->getJson('/api/subscribers/'.$id)
            ->assertOk()
            ->assertJsonPath('data.tags.0.id', $tag->id);
    }
}
