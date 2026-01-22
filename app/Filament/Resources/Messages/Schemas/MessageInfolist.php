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
                Section::make('Dettagli Messaggio')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('subject')
                            ->label('Oggetto'),

                        TextEntry::make('campaign.name')
                            ->label('Campagna'),

                        TextEntry::make('status')
                            ->badge(),

                        TextEntry::make('scheduled_at')
                            ->label('Programmato per')
                            ->dateTime(),

                        TextEntry::make('sent_at')
                            ->label('Inviato il')
                            ->dateTime(),
                    ]),

                Section::make('Contenuto')
                    ->schema([
                        TextEntry::make('html_content')
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Statistiche Invio')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('sends_count')
                            ->label('Invii Totali')
                            ->state(fn (Message $record) => $record->sends()->count()),

                        TextEntry::make('opens_sum')
                            ->label('Aperture Totali')
                            ->state(fn (Message $record) => $record->sends()->sum('opens_count')),

                        TextEntry::make('clicks_sum')
                            ->label('Click Totali')
                            ->state(fn (Message $record) => $record->sends()->sum('clicks_count')),

                        TextEntry::make('failed_count')
                            ->label('Falliti')
                            ->state(fn (Message $record) => $record->sends()->whereNotNull('failed_at')->count()),
                    ]),
            ]);
    }
}
