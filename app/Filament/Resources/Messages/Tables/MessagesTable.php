<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('campaign.name')
                    ->label('Campagna'),

                TextColumn::make('status')
                    ->badge(),

                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MessageStatus::class),

                SelectFilter::make('campaign')
                    ->relationship('campaign', 'name'),
            ])
            ->recordActions([
                Action::make('sendNow')
                    ->label('Invia Ora')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('success')
                    ->visible(fn (Message $record) => $record->status === MessageStatus::Ready)
                    ->requiresConfirmation()
                    ->modalHeading('Invia Messaggio')
                    ->modalDescription('Sei sicuro di voler inviare questo messaggio immediatamente?')
                    ->action(function (Message $record) {
                        $record->update(['status' => MessageStatus::Sending]);

                        // Get target subscribers
                        $query = Subscriber::where('status', SubscriberStatus::Confirmed);

                        if ($record->tags->isNotEmpty()) {
                            $tagIds = $record->tags->pluck('id');
                            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
                        }

                        $subscribers = $query->get();

                        foreach ($subscribers as $subscriber) {
                            $messageSend = MessageSend::create([
                                'message_id' => $record->id,
                                'subscriber_id' => $subscriber->id,
                            ]);

                            SendNewsletterEmail::dispatch($messageSend->id);
                        }

                        Notification::make()
                            ->title('Invio avviato')
                            ->body("Invio in corso a {$subscribers->count()} destinatari.")
                            ->success()
                            ->send();
                    }),

                Action::make('sendTest')
                    ->label('Test')
                    ->icon(Heroicon::Beaker)
                    ->color('warning')
                    ->form([
                        TextInput::make('test_email')
                            ->email()
                            ->required()
                            ->placeholder('test@example.com')
                            ->label('Email di prova'),
                    ])
                    ->action(function (Message $record, array $data) {
                        // Send test email directly without tracking
                        Mail::html($record->html_content, function ($message) use ($record, $data) {
                            $message->to($data['test_email'])
                                ->subject('[TEST] '.$record->subject);
                        });

                        Notification::make()
                            ->title('Email di prova inviata')
                            ->body("Email inviata a {$data['test_email']}")
                            ->success()
                            ->send();
                    }),

                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
