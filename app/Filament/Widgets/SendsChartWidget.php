<?php

namespace App\Filament\Widgets;

use App\Models\MessageSend;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SendsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Invii ultimi 30 giorni';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = MessageSend::query()
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(sent_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing dates with zero
        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $labels[] = Carbon::now()->subDays($i)->format('d/m');
            $values[] = $data[$date] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Email inviate',
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
}
