<?php

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Toggle::make('is_testing')
                    ->label(__('Testing tag'))
                    ->helperText(__('Recipients reached only through testing tags do not appear in newsletter statistics; individual sends are removed from history after the send finishes.'))
                    ->default(false),
            ]);
    }
}
