<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Enums\MessageStatus;
use App\Models\Message;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Contenuto')
                    ->columns(1)
                    ->schema([
                        Select::make('campaign_id')
                            ->relationship('campaign', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('template_id')
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Il template contiene la struttura HTML. Il contenuto del messaggio verrà inserito al posto di {{body}}'),

                        TextInput::make('subject')
                            ->label('Oggetto')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Placeholders: {{name}}, {{email}}'),

                        RichEditor::make('html_content')
                            ->label('Contenuto del messaggio')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('newsletter-images')
                            ->fileAttachmentsVisibility('public')
                            ->toolbarButtons([
                                'attachFiles',
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'h2',
                                'h3',
                                'bulletList',
                                'orderedList',
                                'blockquote',
                                'undo',
                                'redo',
                            ])
                            ->helperText('Questo contenuto verrà inserito nel template al posto di {{body}}. Placeholders: {{name}}, {{email}}'),
                    ]),

                Section::make('Destinatari e Invio')
                    ->columns(1)
                    ->schema([
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Seleziona i tag dei destinatari. Se vuoto, invia a tutti i confermati.'),

                        Select::make('status')
                            ->options(MessageStatus::class)
                            ->default(MessageStatus::Draft)
                            ->required()
                            ->live()
                            ->disabled(fn (?Message $record) => $record?->status === MessageStatus::Sent),

                        DateTimePicker::make('scheduled_at')
                            ->minDate(now())
                            ->visible(fn (Get $get) => $get('status') === MessageStatus::Ready->value),
                    ]),
            ]);
    }
}
