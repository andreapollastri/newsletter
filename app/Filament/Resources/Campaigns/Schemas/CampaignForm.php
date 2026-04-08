<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 500)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (filled($get('slug'))) {
                            return;
                        }
                        $set('slug', Str::slug((string) $state));
                    }),
                TextInput::make('slug')
                    ->label(__('Campaign slug'))
                    ->required()
                    ->maxLength(255)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText(__('Used for UTM tracking on outbound links (utm_campaign). Pre-filled from the name when the slug is empty.')),
            ]);
    }
}
