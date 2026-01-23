<?php

namespace App\Filament\Resources\Messages\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SendsRelationManager extends RelationManager
{
    protected static string $relationship = 'sends';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
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
            ->recordTitleAttribute('subscriber.email')
            ->columns([
                TextColumn::make('subscriber.email')
                    ->label(__('Email'))
                    ->searchable(),

                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime(),

                TextColumn::make('opens_count')
                    ->label(__('Opens')),

                TextColumn::make('clicks_count')
                    ->label(__('Clicks')),

                TextColumn::make('failed_at')
                    ->label(__('Failed'))
                    ->dateTime(),

                TextColumn::make('error_message')
                    ->label(__('Error'))
                    ->limit(30),
            ])
            ->filters([
                //
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
