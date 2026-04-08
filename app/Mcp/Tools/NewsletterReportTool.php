<?php

namespace App\Mcp\Tools;

use App\Models\Campaign;
use App\Services\NewsletterReportingService;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Returns newsletter delivery statistics for a date range: optional campaign filter, summary totals, per-message breakdown, and daily send timeseries.')]
class NewsletterReportTool extends Tool
{
    public function __construct(
        protected NewsletterReportingService $reporting,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::text('Authentication required.');
        }

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'campaign_id' => ['nullable', 'uuid', 'exists:campaigns,id'],
        ], [], [
            'start_date' => 'start date',
            'end_date' => 'end date',
            'campaign_id' => 'campaign id',
        ]);

        if (! empty($validated['campaign_id'])) {
            $owns = $user->id === Campaign::query()->whereKey($validated['campaign_id'])->value('user_id');
            if (! $owns) {
                return Response::text('You do not have access to that campaign.');
            }
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        $payload = $this->reporting->build(
            $user,
            $validated['campaign_id'] ?? null,
            $start,
            $end,
        );

        return Response::json($payload);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'start_date' => $schema->string()
                ->description('Inclusive period start (YYYY-MM-DD).')
                ->required(),
            'end_date' => $schema->string()
                ->description('Inclusive period end (YYYY-MM-DD).')
                ->required(),
            'campaign_id' => $schema->string()
                ->description('Optional campaign UUID to scope the report.')
                ->format('uuid'),
        ];
    }
}
