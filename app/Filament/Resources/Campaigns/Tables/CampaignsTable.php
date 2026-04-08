<?php

namespace App\Filament\Resources\Campaigns\Tables;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Models\Campaign;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('messages_count')
                    ->counts('messages')
                    ->label(__('Messages')),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordUrl(fn (Campaign $record): string => CampaignResource::canEdit($record)
                ? CampaignResource::getUrl('edit', ['record' => $record])
                : CampaignResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (Campaign $record): bool => CampaignResource::canView($record) && ! CampaignResource::canEdit($record)),
                EditAction::make()
                    ->visible(fn (Campaign $record): bool => CampaignResource::canEdit($record)),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
