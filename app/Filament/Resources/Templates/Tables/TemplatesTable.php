<?php

namespace App\Filament\Resources\Templates\Tables;

use App\Filament\Actions\EmailPreviewAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EmailPreviewAction::forTemplate(),
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
