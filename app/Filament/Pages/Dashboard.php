<?php

namespace App\Filament\Pages;

use App\Models\Campaign;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('campaign')
                            ->label(__('Campaign'))
                            ->options(fn () => Campaign::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->placeholder(__('All campaigns'))
                            ->live(),

                        Select::make('period')
                            ->label(__('Time Period'))
                            ->options([
                                '24h' => __('Last 24 hours'),
                                '7d' => __('Last 7 days'),
                                '1m' => __('Last month'),
                                '6m' => __('Last 6 months'),
                                '1y' => __('Last year'),
                            ])
                            ->default('1m')
                            ->required()
                            ->live(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }
}
