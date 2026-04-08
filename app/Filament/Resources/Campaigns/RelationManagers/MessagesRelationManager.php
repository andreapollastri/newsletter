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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                if (Auth::user()?->isEditor()) {
                    $query->whereNotIn('status', [MessageStatus::Sent, MessageStatus::Sending]);
                }

                $query->excludeSentTestingOnly();

                return $query;
            })
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
                    ->url(fn ($record) => MessageResource::getUrl('view', ['record' => $record]))
                    ->visible(fn ($record): bool => MessageResource::canView($record)),
                EditAction::make()
                    ->url(fn ($record) => MessageResource::getUrl('edit', ['record' => $record]))
                    ->visible(fn ($record): bool => MessageResource::canEdit($record)),
                DeleteAction::make()
                    ->visible(fn ($record): bool => MessageResource::canDelete($record)),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
