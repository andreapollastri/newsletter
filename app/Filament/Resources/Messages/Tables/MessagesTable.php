<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
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
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('subject')
                    ->label(__('Message'))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->description(fn (Message $record): ?string => $record->campaign?->name),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),

                TextColumn::make('scheduled_at')
                    ->label(__('Scheduled At'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (Message $record): ?string => $record->scheduled_at && $record->scheduled_at->isFuture()
                        ? __('Automatic sending in :time', ['time' => $record->scheduled_at->diffForHumans()])
                        : null),

                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (Message $record): ?string => $record->sent_at
                        ? __(':time ago', ['time' => $record->sent_at->diffForHumans()])
                        : null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MessageStatus::class),

                SelectFilter::make('campaign')
                    ->relationship('campaign', 'name'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->visible(fn (Message $record) => $record->status === MessageStatus::Sent),
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('sendNow')
                        ->label(__('Send Now'))
                        ->icon(Heroicon::PaperAirplane)
                        ->color('success')
                        ->visible(fn (Message $record) => $record->status === MessageStatus::Ready)
                        ->requiresConfirmation()
                        ->modalHeading(__('Send Message'))
                        ->modalDescription(__('Are you sure you want to send this message immediately?'))
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
                                ->title(__('Sending started'))
                                ->body(__('Sending in progress to :count recipients.', ['count' => $subscribers->count()]))
                                ->success()
                                ->send();
                        }),
                    Action::make('sendTest')
                        ->label(__('Send Test'))
                        ->icon(Heroicon::Beaker)
                        ->color('warning')
                        ->form([
                            TextInput::make('test_email')
                                ->email()
                                ->required()
                                ->placeholder('test@example.com')
                                ->label(__('Test Email')),
                        ])
                        ->action(function (Message $record, array $data) {
                            // Send test email directly without tracking
                            Mail::html($record->html_content, function ($message) use ($record, $data) {
                                $message->to($data['test_email'])
                                    ->subject('[TEST] '.$record->subject);
                            });

                            Notification::make()
                                ->title(__('Test email sent'))
                                ->body(__('Email sent to :email', ['email' => $data['test_email']]))
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->visible(fn (Message $record) => $record->status !== MessageStatus::Sent && $record->status !== MessageStatus::Sending),
                ])
                    ->visible(fn (Message $record) => $record->status !== MessageStatus::Sent),
            ]);
    }
}
