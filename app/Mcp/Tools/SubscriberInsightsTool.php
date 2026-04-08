<?php

namespace App\Mcp\Tools;

use App\Models\Subscriber;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Summarizes subscriber counts by status and lists tags with the most subscribers for audience planning.')]
class SubscriberInsightsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::text('Authentication required.');
        }

        $byStatus = Subscriber::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $topTags = Tag::query()
            ->withCount('subscribers')
            ->orderByDesc('subscribers_count')
            ->limit(15)
            ->get(['id', 'name', 'is_testing', 'subscribers_count']);

        return Response::json([
            'subscribers_by_status' => $byStatus,
            'top_tags' => $topTags,
            'total_subscribers' => Subscriber::query()->count(),
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
