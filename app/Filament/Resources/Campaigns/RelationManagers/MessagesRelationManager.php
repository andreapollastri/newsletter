<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->searchable()
                    ->limit(40),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),

                TextColumn::make('scheduled_at')
                    ->label(__('Scheduled At'))
                    ->dateTime(),

                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => MessageResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn ($record) => MessageResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn ($record) => $record->status === MessageStatus::Draft || $record->status === MessageStatus::Ready),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->status === MessageStatus::Draft || $record->status === MessageStatus::Ready),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
