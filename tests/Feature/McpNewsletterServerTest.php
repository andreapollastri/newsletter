<?php

namespace Tests\Feature;

use App\Mcp\Servers\NewsletterMcpServer;
use App\Mcp\Tools\ListCampaignsTool;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpNewsletterServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_campaigns_tool_returns_user_campaigns(): void
    {
        $user = User::factory()->create();
        Campaign::factory()->create(['user_id' => $user->id, 'name' => 'Alpha']);
        Campaign::factory()->create();

        $response = NewsletterMcpServer::actingAs($user)->tool(ListCampaignsTool::class);

        $response->assertOk();
        $response->assertSee('Alpha');
    }
}
