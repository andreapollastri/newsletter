<?php

namespace App\Filament\Resources\Messages\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SendsRelationManager extends RelationManager
{
    protected static string $relationship = 'sends';

    protected static ?string $title = 'Invii';

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
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('sent_at')
                    ->label('Inviato')
                    ->dateTime(),

                TextColumn::make('opens_count')
                    ->label('Aperture'),

                TextColumn::make('clicks_count')
                    ->label('Click'),

                TextColumn::make('failed_at')
                    ->label('Fallito')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error_message')
                    ->label('Errore')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
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
