<?php

namespace App\Filament\Resources\Subscribers\Schemas;

use App\Enums\SubscriberStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('name')
                    ->label(__('Name'))
                    ->maxLength(255),

                Select::make('status')
                    ->label(__('Status'))
                    ->options(SubscriberStatus::class)
                    ->default(SubscriberStatus::Pending)
                    ->required(),

                Select::make('tags')
                    ->label(__('Tags'))
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                    ]),
            ]);
    }
}
