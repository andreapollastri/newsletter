<?php

namespace App\Mcp\Tools;

use App\Models\Campaign;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lists newsletter campaigns: all campaigns for managers/administrators; only campaigns created by the user for editors (id, name, slug, description).')]
class ListCampaignsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::text('Authentication required.');
        }

        $campaigns = Campaign::query()
            ->when(! $user->canAccessManagementFeatures(), fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'created_at', 'updated_at']);

        return Response::json([
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
