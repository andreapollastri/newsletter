<?php

namespace App\Services;

use App\Models\Bounce;
use App\Models\Campaign;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NewsletterReportingService
{
    /**
     * @return array{
     *     filters: array{campaign_id: ?string, start_date: string, end_date: string},
     *     summary: array{
     *         sends: int,
     *         failed_sends: int,
     *         opens: int,
     *         clicks: int,
     *         bounces: int,
     *         unique_messages_with_sends: int
     *     },
     *     by_message: list<array{
     *         message_id: string,
     *         subject: string,
     *         campaign_id: string,
     *         campaign_name: string,
     *         sends: int,
     *         failed_sends: int,
     *         opens: int,
     *         clicks: int
     *     }>,
     *     timeseries: list<array{date: string, sends: int}>
     * }
     */
    public function build(User $user, ?string $campaignId, Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $campaignQuery = Campaign::query();
        if (! $user->canAccessManagementFeatures()) {
            $campaignQuery->where('user_id', $user->id);
        }
        if ($campaignId !== null) {
            $campaignQuery->where('id', $campaignId);
        }

        $campaignIds = $campaignQuery->pluck('id');
        if ($campaignIds->isEmpty()) {
            return [
                'filters' => [
                    'campaign_id' => $campaignId,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->copy()->startOfDay()->toDateString(),
                ],
                'summary' => [
                    'sends' => 0,
                    'failed_sends' => 0,
                    'opens' => 0,
                    'clicks' => 0,
                    'bounces' => 0,
                    'unique_messages_with_sends' => 0,
                ],
                'by_message' => [],
                'timeseries' => [],
            ];
        }

        $messageIds = Message::query()
            ->whereIn('campaign_id', $campaignIds)
            ->pluck('id');

        if ($messageIds->isEmpty()) {
            return [
                'filters' => [
                    'campaign_id' => $campaignId,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->copy()->startOfDay()->toDateString(),
                ],
                'summary' => [
                    'sends' => 0,
                    'failed_sends' => 0,
                    'opens' => 0,
                    'clicks' => 0,
                    'bounces' => 0,
                    'unique_messages_with_sends' => 0,
                ],
                'by_message' => [],
                'timeseries' => [],
            ];
        }

        $sendBase = MessageSend::query()
            ->forStatistics()
            ->whereIn('message_id', $messageIds);

        $sendsInPeriod = (clone $sendBase)
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$start, $end]);

        $failedInPeriod = (clone $sendBase)
            ->whereNotNull('failed_at')
            ->whereBetween('failed_at', [$start, $end]);

        $sendsCount = (clone $sendsInPeriod)->count();
        $failedCount = (clone $failedInPeriod)->count();

        $opensSum = (int) (clone $sendsInPeriod)->sum('opens_count');
        $clicksSum = (int) (clone $sendsInPeriod)->sum('clicks_count');

        $sendIdsInPeriod = (clone $sendsInPeriod)->pluck('id');

        $bouncesCount = $sendIdsInPeriod->isEmpty()
            ? 0
            : Bounce::query()
                ->whereIn('message_send_id', $sendIdsInPeriod)
                ->whereBetween('detected_at', [$start, $end])
                ->count();

        $uniqueMessagesWithSends = (int) (clone $sendsInPeriod)->selectRaw('COUNT(DISTINCT message_id) as aggregate')->value('aggregate');

        $byMessage = Message::query()
            ->with('campaign:id,name')
            ->whereIn('id', $messageIds)
            ->get()
            ->keyBy('id');

        $perMessageStats = (clone $sendsInPeriod)
            ->select([
                'message_id',
                DB::raw('COUNT(*) as sends'),
                DB::raw('COALESCE(SUM(opens_count), 0) as opens'),
                DB::raw('COALESCE(SUM(clicks_count), 0) as clicks'),
            ])
            ->groupBy('message_id')
            ->get();

        $failedByMessage = (clone $failedInPeriod)
            ->select([
                'message_id',
                DB::raw('COUNT(*) as failed_sends'),
            ])
            ->groupBy('message_id')
            ->pluck('failed_sends', 'message_id');

        $byMessageRows = [];
        foreach ($perMessageStats as $row) {
            /** @var Message|null $message */
            $message = $byMessage->get($row->message_id);
            if ($message === null) {
                continue;
            }

            $byMessageRows[] = [
                'message_id' => (string) $row->message_id,
                'subject' => $message->subject,
                'campaign_id' => (string) $message->campaign_id,
                'campaign_name' => $message->campaign?->name ?? '',
                'sends' => (int) $row->sends,
                'failed_sends' => (int) ($failedByMessage[$row->message_id] ?? 0),
                'opens' => (int) $row->opens,
                'clicks' => (int) $row->clicks,
            ];
        }

        usort($byMessageRows, fn (array $a, array $b): int => $b['sends'] <=> $a['sends']);

        $timeseriesRaw = (clone $sendsInPeriod)
            ->select([DB::raw('DATE(sent_at) as date'), DB::raw('COUNT(*) as sends')])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $timeseries = $timeseriesRaw->map(fn ($r): array => [
            'date' => (string) $r->date,
            'sends' => (int) $r->sends,
        ])->values()->all();

        return [
            'filters' => [
                'campaign_id' => $campaignId,
                'start_date' => $start->toDateString(),
                'end_date' => $end->copy()->startOfDay()->toDateString(),
            ],
            'summary' => [
                'sends' => $sendsCount,
                'failed_sends' => $failedCount,
                'opens' => $opensSum,
                'clicks' => $clicksSum,
                'bounces' => $bouncesCount,
                'unique_messages_with_sends' => $uniqueMessagesWithSends,
            ],
            'by_message' => $byMessageRows,
            'timeseries' => $timeseries,
        ];
    }
}
