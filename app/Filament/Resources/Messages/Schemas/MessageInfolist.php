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
                Section::make(__('Send Statistics'))
                    ->columns(7)
                    ->schema([
                        TextEntry::make('total_sends')
                            ->label(__('Total Sends'))
                            ->state(fn (Message $record) => $record->sends()->count())
                            ->numeric(),

                        TextEntry::make('total_opens')
                            ->label(__('Total Opens'))
                            ->state(fn (Message $record) => $record->sends()->sum('opens_count'))
                            ->numeric(),

                        TextEntry::make('unique_opens')
                            ->label(__('Unique Opens'))
                            ->state(fn (Message $record) => $record->sends()->where('opens_count', '>', 0)->count())
                            ->numeric(),

                        TextEntry::make('total_clicks')
                            ->label(__('Total Clicks'))
                            ->state(fn (Message $record) => $record->sends()->sum('clicks_count'))
                            ->numeric(),

                        TextEntry::make('unique_clicks')
                            ->label(__('Unique Clicks'))
                            ->state(fn (Message $record) => $record->sends()->where('clicks_count', '>', 0)->count())
                            ->numeric(),

                        TextEntry::make('failed_sends')
                            ->label(__('Failed Sends'))
                            ->state(fn (Message $record) => $record->sends()->whereNotNull('failed_at')->count())
                            ->numeric(),

                        TextEntry::make('unsubscribes')
                            ->label(__('Unsubscribes'))
                            ->state(fn (Message $record) => \App\Models\Subscriber::where('unsubscribed_from_message_id', $record->id)->count())
                            ->numeric(),
                    ])
                    ->columnSpanFull(),

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
                    ])
                    ->columnSpanFull(),

                Section::make(__('Content'))
                    ->schema([
                        TextEntry::make('html_content')
                            ->label(__('HTML Content'))
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
