<?php

namespace App\Filament\Resources\Tags\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_testing')
                    ->label(__('Testing'))
                    ->boolean()
                    ->trueIcon(Heroicon::Beaker)
                    ->falseIcon(Heroicon::OutlinedMinus)
                    ->alignCenter(),

                TextColumn::make('subscribers_count')
                    ->counts('subscribers')
                    ->sortable()
                    ->label(__('Subscribers')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
