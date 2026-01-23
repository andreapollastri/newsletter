<?php

namespace App\Filament\Widgets;

use App\Models\MessageSend;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SendsChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): ?string
    {
        $period = $this->pageFilters['period'] ?? '1m';
        $periodLabel = $this->getPeriodLabel($period);

        return __('Sends').' - '.$periodLabel;
    }

    protected function getData(): array
    {
        $period = $this->pageFilters['period'] ?? '1m';
        $startDate = $this->getStartDateForPeriod($period);

        if ($period === '24h') {
            return $this->getHourlyData($startDate);
        }

        $data = MessageSend::query()
            ->whereNotNull('sent_at')
            ->when($startDate, fn (Builder $query) => $query->where('sent_at', '>=', $startDate))
            ->select(DB::raw('DATE(sent_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        $days = $this->getDaysForPeriod($period);

        // Fill in missing dates with zero
        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('d/m');
            $values[] = $data[$date] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Emails sent'),
                    'data' => $values,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getHourlyData(Carbon $startDate): array
    {
        // Use database-agnostic approach: get all records and group in PHP
        $records = MessageSend::query()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', $startDate)
            ->get();

        $data = [];
        foreach ($records as $record) {
            $hour = $record->sent_at->format('Y-m-d H:00:00');
            $data[$hour] = ($data[$hour] ?? 0) + 1;
        }

        $labels = [];
        $values = [];

        // Generate labels for last 24 hours (grouped by hour)
        for ($i = 23; $i >= 0; $i--) {
            $hour = Carbon::now()->subHours($i)->format('Y-m-d H:00:00');
            $label = Carbon::now()->subHours($i)->format('H:i');
            $labels[] = $label;
            $values[] = $data[$hour] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => __('Emails sent'),
                    'data' => $values,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getStartDateForPeriod(?string $period): ?Carbon
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
