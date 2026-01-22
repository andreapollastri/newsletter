<?php

namespace App\Filament\Resources\Messages\Schemas;

use App\Enums\MessageStatus;
use App\Models\Message;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                    ->columns(2)
                    ->schema([
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Seleziona i tag dei destinatari. Se vuoto, invia a tutti i confermati.'),

                        Select::make('status')
                            ->options([
                                MessageStatus::Draft->value => MessageStatus::Draft->getLabel(),
                                MessageStatus::Ready->value => MessageStatus::Ready->getLabel(),
                            ])
                            ->default(MessageStatus::Draft)
                            ->required()
                            ->live()
                            ->disabled(fn (?Message $record) => $record?->status === MessageStatus::Sent || $record?->status === MessageStatus::Sending)
                            ->helperText(fn (?Message $record) => match ($record?->status) {
                                MessageStatus::Sent => 'Messaggio già inviato, impossibile modificare lo status',
                                MessageStatus::Sending => 'Messaggio in invio, impossibile modificare lo status',
                                default => 'Seleziona "Pronto" per programmare o inviare il messaggio'
                            }),

                        DateTimePicker::make('scheduled_at')
                            ->label('Data e ora programmazione')
                            ->minDate(now())
                            ->seconds(false)
                            ->native(false)
                            ->helperText('Opzionale: programma invio automatico. Imposta lo status su "Pronto" per attivare l\'invio programmato.')
                            ->disabled(fn (?Message $record) => $record?->status === MessageStatus::Sent || $record?->status === MessageStatus::Sending),
                    ]),
            ]);
    }
}
