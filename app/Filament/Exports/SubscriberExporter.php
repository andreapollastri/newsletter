<?php

namespace App\Filament\Exports;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class SubscriberExporter extends Exporter
{
    protected static ?string $model = Subscriber::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('email'),

            ExportColumn::make('name'),

            ExportColumn::make('status')
                ->formatStateUsing(fn (SubscriberStatus $state): string => $state->getLabel()),

            ExportColumn::make('tags')
                ->state(fn (Subscriber $record): string => $record->tags->pluck('name')->join(', ')),

            ExportColumn::make('confirmed_at')
                ->formatStateUsing(fn (?Carbon $state): ?string => $state?->format('Y-m-d H:i')),

            ExportColumn::make('created_at')
                ->formatStateUsing(fn (Carbon $state): string => $state->format('Y-m-d H:i')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('Export subscribers completed: :count row(s) exported.', [
            'count' => Number::format($export->successful_rows),
        ]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.__(':count row(s) failed.', [
                'count' => Number::format($failedRowsCount),
            ]);
        }

        return $body;
    }
}
