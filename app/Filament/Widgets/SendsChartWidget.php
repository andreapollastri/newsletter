<?php

namespace App\Filament\Widgets;

use App\Models\Message;
use App\Models\MessageOpen;
use App\Models\MessageSend;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class SendsChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    public function getHeading(): ?string
    {
        $period = $this->pageFilters['period'] ?? '1m';
        $periodLabel = $this->getPeriodLabel($period);

        return __('Sends').' - '.$periodLabel;
    }

    /**
     * Emails sent and unique opens per day (per hour for the last 24 hours).
     *
     * @return array{datasets: list<array<string, mixed>>, labels: list<string>}
     */
    protected function getData(): array
    {
        $period = $this->pageFilters['period'] ?? '1m';
        $campaignId = $this->pageFilters['campaign'] ?? null;
        $startDate = $this->getStartDateForPeriod($period);

        $messageIds = $campaignId
            ? Message::where('campaign_id', $campaignId)->pluck('id')
            : null;

        $sends = MessageSend::query()
            ->forStatistics()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $startDate)
            ->when($messageIds, fn (Builder $query) => $query->whereIn('message_id', $messageIds));

        $opens = MessageOpen::query()
            ->where('opened_at', '>=', $startDate)
            ->whereHas('messageSend', fn (Builder $query) => $query
                ->forStatistics()
                ->when($messageIds, fn (Builder $q) => $q->whereIn('message_id', $messageIds)));

        $buckets = $this->getBuckets($period);

        return [
            'datasets' => [
                [
                    'label' => __('Emails sent'),
                    'data' => $this->countPerBucket($sends, 'sent_at', $buckets),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.75)',
                    'borderColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
                [
                    'label' => __('Opens'),
                    'data' => $this->countPerBucket($opens, 'opened_at', $buckets),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor' => '#10b981',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_values($buckets),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Bucket keys (Y-m-d or Y-m-d H for the last 24 hours) mapped to chart labels, oldest first.
     *
     * @return array<string, string>
     */
    protected function getBuckets(string $period): array
    {
        $buckets = [];

        if ($period === '24h') {
            for ($i = 23; $i >= 0; $i--) {
                $hour = Carbon::now()->subHours($i);
                $buckets[$hour->format('Y-m-d H')] = $hour->format('H:00');
            }

            return $buckets;
        }

        for ($i = $this->getDaysForPeriod($period) - 1; $i >= 0; $i--) {
            $day = Carbon::now()->subDays($i);
            $buckets[$day->format('Y-m-d')] = $day->format('d/m');
        }

        return $buckets;
    }

    /**
     * Daily buckets are grouped by the database; the 24-hour window is small enough to group in PHP,
     * which avoids database-specific hour functions.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string>  $buckets
     * @return list<int>
     */
    protected function countPerBucket(Builder $query, string $column, array $buckets): array
    {
        if (str_contains((string) array_key_first($buckets), ' ')) {
            $counts = $query
                ->pluck($column)
                ->filter()
                ->countBy(fn (Carbon $timestamp): string => $timestamp->format('Y-m-d H'));
        } else {
            $counts = $query
                ->selectRaw("DATE({$column}) as bucket, COUNT(*) as aggregate")
                ->groupBy('bucket')
                ->pluck('aggregate', 'bucket');
        }

        return array_map(fn (string $bucket): int => (int) ($counts[$bucket] ?? 0), array_keys($buckets));
    }

    protected function getStartDateForPeriod(?string $period): Carbon
    {
        return match ($period) {
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '1m' => now()->subMonth(),
            '6m' => now()->subMonths(6),
            '1y' => now()->subYear(),
            default => now()->subMonth(),
        };
    }

    protected function getDaysForPeriod(string $period): int
    {
        return match ($period) {
            '24h' => 1,
            '7d' => 7,
            '1m' => 30,
            '6m' => 180,
            '1y' => 365,
            default => 30,
        };
    }

    protected function getPeriodLabel(string $period): string
    {
        return match ($period) {
            '24h' => __('Last 24 hours'),
            '7d' => __('Last 7 days'),
            '1m' => __('Last month'),
            '6m' => __('Last 6 months'),
            '1y' => __('Last year'),
            default => __('Last month'),
        };
    }
}
