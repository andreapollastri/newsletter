<?php

namespace App\Filament\Resources\Messages\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SendsRelationManager extends RelationManager
{
    protected static string $relationship = 'sends';

    protected static ?string $title = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Sends');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only relation manager
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['subscriber.tags']))
            ->recordTitleAttribute('subscriber.email')
            ->columns([
                TextColumn::make('subscriber.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('opens_count')
                    ->label(__('Opens'))
                    ->sortable(),

                TextColumn::make('clicks_count')
                    ->label(__('Clicks'))
                    ->sortable(),

                TextColumn::make('failed_at')
                    ->label(__('Failed'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('error_message')
                    ->label(__('Error'))
                    ->limit(30)
                    ->sortable(),
            ])
            ->filters([
                Filter::make('hide_test_only_subscribers')
                    ->label(__('Hide sends to test-only subscribers'))
                    ->toggle()
                    ->default(true)
                    ->query(function (Builder $query, array $data): void {
                        $query->whereDoesntHave('subscriber', function (Builder $q): void {
                            $q->whereHas('tags')
                                ->whereDoesntHave('tags', fn (Builder $tq) => $tq->where('is_testing', false));
                        });
                    }),
            ])
            ->headerActions([
                // No create action - sends are created by jobs
            ])
            ->recordActions([
                // No edit/delete actions - read only
            ])
            ->toolbarActions([
                // No bulk actions
            ]);
    }
}
