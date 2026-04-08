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

#[Description('Combines the newsletter report with short textual highlights (totals, best-performing message by sends) for quick interpretation.')]
class SendHistoryAnalysisTool extends Tool
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
        ]);

        if (! empty($validated['campaign_id'])) {
            $campaign = Campaign::query()->findOrFail($validated['campaign_id']);
            if (! $user->canAccessCampaign($campaign)) {
                return Response::text('You do not have access to that campaign.');
            }
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->endOfDay();

        $report = $this->reporting->build(
            $user,
            $validated['campaign_id'] ?? null,
            $start,
            $end,
        );

        $highlights = [];
        $summary = $report['summary'];
        $highlights[] = "Sends recorded: {$summary['sends']}, failed: {$summary['failed_sends']}, tracked opens (sum of per-send counters): {$summary['opens']}, clicks (sum): {$summary['clicks']}, bounces in window: {$summary['bounces']}.";

        $byMessage = $report['by_message'];
        if ($byMessage !== []) {
            $top = $byMessage[0];
            $highlights[] = "Top message by volume: «{$top['subject']}» ({$top['sends']} sends, campaign {$top['campaign_name']}).";
        } else {
            $highlights[] = 'No sends with sent_at in this period for the selected scope.';
        }

        if (($report['timeseries'] ?? []) !== []) {
            $days = count($report['timeseries']);
            $highlights[] = "Daily send series has {$days} day bucket(s) in range.";
        }

        return Response::json([
            'report' => $report,
            'highlights' => $highlights,
        ]);
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
                ->description('Optional campaign UUID.')
                ->format('uuid'),
        ];
    }
}
