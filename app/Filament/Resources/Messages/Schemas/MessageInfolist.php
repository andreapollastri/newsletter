<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Models\Message;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Message Details'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subject')
                            ->label(__('Subject')),

                        TextEntry::make('campaign.name')
                            ->label(__('Campaign')),

                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge(),

                        TextEntry::make('scheduled_at')
                            ->label(__('Scheduled At'))
                            ->dateTime(),

                        TextEntry::make('sent_at')
                            ->label(__('Sent At'))
                            ->dateTime(),
                    ]),

                Section::make(__('Content'))
                    ->schema([
                        TextEntry::make('html_content')
                            ->label(__('HTML Content'))
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Send Statistics'))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('sends_count')
                            ->label(__('Total Sends'))
                            ->state(fn (Message $record) => $record->sends()->count()),

                        TextEntry::make('opens_sum')
                            ->label(__('Total Opens'))
                            ->state(fn (Message $record) => $record->sends()->sum('opens_count')),

                        TextEntry::make('clicks_sum')
                            ->label(__('Total Clicks'))
                            ->state(fn (Message $record) => $record->sends()->sum('clicks_count')),

                        TextEntry::make('failed_count')
                            ->label(__('Failed'))
                            ->state(fn (Message $record) => $record->sends()->whereNotNull('failed_at')->count()),
                    ]),
            ]);
    }
}
