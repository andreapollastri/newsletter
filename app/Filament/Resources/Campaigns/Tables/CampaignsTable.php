<?php

namespace App\Filament\Resources\Campaigns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label(__('Messages')),

                TextColumn::make('user.name')
                    ->label(__('Created By')),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            $recordsWithMessages = $records->filter(fn ($record) => $record->messages()->exists());

                            if ($recordsWithMessages->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title(__('Cannot delete campaigns with associated messages'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $records->each->delete();

                            \Filament\Notifications\Notification::make()
                                ->title(__('Campaigns deleted successfully'))
                                ->success()
                                ->send();
                        }),
                    ForceDeleteBulkAction::make()
                        ->action(function ($records) {
                            $recordsWithMessages = $records->filter(fn ($record) => $record->messages()->exists());

                            if ($recordsWithMessages->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title(__('Cannot delete campaigns with associated messages'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $records->each->forceDelete();

                            \Filament\Notifications\Notification::make()
                                ->title(__('Campaigns permanently deleted'))
                                ->success()
                                ->send();
                        }),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
