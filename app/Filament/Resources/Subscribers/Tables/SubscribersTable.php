<?php

namespace App\Filament\Resources\Subscribers\Tables;

use App\Enums\SubscriberStatus;
use App\Filament\Exports\SubscriberExporter;
use App\Filament\Imports\SubscriberImporter;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),

                TextColumn::make('tags.name')
                    ->label(__('Tags'))
                    ->badge()
                    ->separator(','),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SubscriberStatus::class),

                SelectFilter::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload(),

                TrashedFilter::make(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(SubscriberImporter::class),
                ExportAction::make()
                    ->exporter(SubscriberExporter::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
