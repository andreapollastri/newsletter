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
                Section::make(__('Content'))
                    ->columns(1)
                    ->schema([
                        Select::make('campaign_id')
                            ->label(__('Campaign'))
                            ->relationship('campaign', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('template_id')
                            ->label(__('Template'))
                            ->relationship('template', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText(__('The template contains the HTML structure. The message content will be inserted in place of {{body}}')),

                        TextInput::make('subject')
                            ->label(__('Subject'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('Placeholders: {{name}}, {{email}}')),

                        RichEditor::make('html_content')
                            ->label(__('Message Content'))
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
                                'alignLeft',
                                'alignCenter',
                                'alignRight',
                                'undo',
                                'redo',
                            ])
                            ->helperText(__('This content will be inserted into the template in place of {{body}}. Placeholders: {{name}}, {{email}}')),
                    ]),

                Section::make(__('Recipients and Sending'))
                    ->columns(2)
                    ->schema([
                        Select::make('tags')
                            ->label(__('Tags'))
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText(__('Select recipient tags. If empty, send to all confirmed.')),

                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                MessageStatus::Draft->value => MessageStatus::Draft->getLabel(),
                                MessageStatus::Ready->value => MessageStatus::Ready->getLabel(),
                            ])
                            ->default(MessageStatus::Draft)
                            ->required()
                            ->live()
                            ->disabled(fn (?Message $record) => $record?->status === MessageStatus::Sent || $record?->status === MessageStatus::Sending)
                            ->helperText(fn (?Message $record) => match ($record?->status) {
                                MessageStatus::Sent => __('Message already sent, cannot modify status'),
                                MessageStatus::Sending => __('Message sending, cannot modify status'),
                                default => __('Select "Ready" to schedule or send the message')
                            }),

                        DateTimePicker::make('scheduled_at')
                            ->label(__('Scheduled Date and Time'))
                            ->minDate(now())
                            ->seconds(false)
                            ->native(false)
                            ->helperText(__('Optional: schedule automatic sending. Set status to "Ready" to activate scheduled sending.'))
                            ->disabled(fn (?Message $record) => $record?->status === MessageStatus::Sent || $record?->status === MessageStatus::Sending),
                    ]),
            ]);
    }
}
