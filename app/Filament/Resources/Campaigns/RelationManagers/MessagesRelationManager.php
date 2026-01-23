<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('subject')
                    ->label(__('Subject'))
                    ->required()
                    ->maxLength(255),

                RichEditor::make('html_content')
                    ->label(__('HTML Content'))
                    ->required(),

                Select::make('status')
                    ->label(__('Status'))
                    ->options(MessageStatus::class)
                    ->default(MessageStatus::Draft)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->columns([
                TextColumn::make('subject')
                    ->label(__('Subject'))
                    ->searchable()
                    ->limit(40),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),

                TextColumn::make('scheduled_at')
                    ->label(__('Scheduled At'))
                    ->dateTime(),

                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => MessageResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
