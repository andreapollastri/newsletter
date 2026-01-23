<?php

namespace App\Filament\Resources\Subscribers\RelationManagers;

use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessageSendsRelationManager extends RelationManager
{
    protected static string $relationship = 'messageSends';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Messages Received');
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
            ->recordTitleAttribute('message.subject')
            ->modifyQueryUsing(fn ($query) => $query->with(['message.campaign']))
            ->columns([
                TextColumn::make('message.subject')
                    ->label(__('Subject'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('message.campaign.name')
                    ->label(__('Campaign'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create action - sends are created by jobs
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => MessageResource::getUrl('view', ['record' => $record->message])),
            ])
            ->toolbarActions([
                // No bulk actions
            ])
            ->defaultSort('sent_at', 'desc');
    }
}
