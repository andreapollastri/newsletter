<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->label(__('Description'))
                    ->rows(3),
            ]);
    }
}
