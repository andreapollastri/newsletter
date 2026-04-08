<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\UserLocale;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    /**
     * @param  ?User  $record  Set when editing an existing user (for role field rules).
     */
    public static function configure(Schema $schema, ?User $record = null): Schema
    {
        $localeOptions = collect(UserLocale::ALLOWED)
            ->mapWithKeys(fn (string $code): array => [$code => match ($code) {
                'it' => __('Italian'),
                'en' => __('English'),
                'de' => __('German'),
                'fr' => __('French'),
                'es' => __('Spanish'),
                'pt' => __('Portuguese'),
                default => $code,
            }])
            ->all();

        return $schema
            ->components([
                Section::make(__('Account'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('Email address'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('role')
                            ->label(__('Role'))
                            ->options(UserRole::class)
                            ->required()
                            ->default(UserRole::Editor)
                            ->disabled($record?->is(auth()->user()) ?? false)
                            ->helperText($record?->is(auth()->user())
                                ? __('Only another administrator can change your role.')
                                : null),

                        Select::make('locale')
                            ->label(__('Language'))
                            ->options($localeOptions)
                            ->required()
                            ->native(false),

                        TextInput::make('password')
                            ->label(__('Password'))
                            ->password()
                            ->revealable()
                            ->required(fn (): bool => $record === null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->maxLength(255)
                            ->helperText($record !== null ? __('Leave blank to keep the current password.') : null)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
