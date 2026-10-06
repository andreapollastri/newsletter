<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageStatus;
use App\Filament\Actions\EmailPreviewAction;
use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use App\Services\MessageDispatchService;
use App\Support\NewsletterHtml;
use Carbon\CarbonInterface;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class MessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (Message $record): string => $record->status === MessageStatus::Sent
                ? MessageResource::getUrl('view', ['record' => $record])
                : MessageResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('subject')
                    ->label(__('Message'))
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->description(fn (Message $record): ?string => $record->campaign?->name),

                TextColumn::make('audience_tags')
                    ->label(__('Audience'))
                    ->badge()
                    ->state(fn (Message $record): array => $record->audienceLabels())
                    ->color(function (string $state, Message $record): string {
                        if ($state === __('All subscribers')) {
                            return 'warning';
                        }

                        return $record->excludedTagLabels()->contains($state) ? 'danger' : 'info';
                    }),

                TextColumn::make('emails_sent_count')
                    ->label(__('Emails sent'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('opens_sum')
                    ->label(__('Opens (total)'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(fn (?int $state): int => (int) ($state ?? 0)),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->description(fn (Message $record): ?string => $record->getEstimatedSendTime()),

                TextColumn::make('scheduled_at')
                    ->label(__('Scheduled At'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (Message $record): ?string => $record->scheduled_at && $record->scheduled_at->isFuture()
                        ? __('Automatic sending in :time', ['time' => $record->scheduled_at->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE)])
                        : null),

                TextColumn::make('sent_at')
                    ->label(__('Sent At'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn (Message $record): ?string => $record->sent_at
                        ? $record->sent_at->diffForHumans()
                        : null),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(MessageStatus::class),

                SelectFilter::make('campaign')
                    ->label(__('Campaign'))
                    ->relationship('campaign', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (Message $record): bool => $record->status === MessageStatus::Sent
                            && MessageResource::canView($record)),
                    EditAction::make()
                        ->visible(fn (Message $record): bool => $record->status !== MessageStatus::Sent
                            && MessageResource::canEdit($record)),
                    Action::make('sendNow')
                        ->label(__('Send Now'))
                        ->icon(Heroicon::PaperAirplane)
                        ->color('success')
                        ->visible(fn (Message $record): bool => $record->status === MessageStatus::Ready
                            && Auth::user() !== null
                            && ! Auth::user()->isEditor())
                        ->requiresConfirmation()
                        ->modalHeading(__('Send Message'))
                        ->modalDescription(__('Are you sure you want to send this message immediately?'))
                        ->action(function (Message $record, MessageDispatchService $dispatcher): void {
                            if ($dispatcher->dispatch($record) === 0) {
                                Notification::make()
                                    ->title(__('No recipients'))
                                    ->body(__('No confirmed subscribers match this message audience.'))
                                    ->warning()
                                    ->send();
                            }
                        }),
                    EmailPreviewAction::forMessage(),
                    Action::make('sendTest')
                        ->label(__('Send Test'))
                        ->icon(Heroicon::Beaker)
                        ->color('warning')
                        ->visible(fn (Message $record): bool => $record->status !== MessageStatus::Sent
                            && Auth::user() !== null
                            && ! Auth::user()->isEditor())
                        ->form([
                            TextInput::make('test_email')
                                ->email()
                                ->required()
                                ->placeholder('test@example.com')
                                ->label(__('Test Email')),
                        ])
                        ->action(function (Message $record, array $data) {
                            // Same markup as a real send, with visible placeholder values and no tracking
                            $subject = NewsletterHtml::fillPlaceholders($record->subject, 'NAME', 'EMAIL', '#');
                            $htmlContent = NewsletterHtml::fillPlaceholders(
                                NewsletterHtml::absolutizeImageSources(NewsletterHtml::compose($record)),
                                'NAME',
                                'EMAIL',
                                '#',
                            );

                            // Send test email directly without tracking
                            Mail::html($htmlContent, function ($message) use ($subject, $data) {
                                $message->to($data['test_email'])
                                    ->subject('[TEST] '.$subject);

                                $message->getSymfonyMessage()->getHeaders()
                                    ->addTextHeader('List-Unsubscribe', '<'.route('unsubscribe.test').'>')
                                    ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                            });

                            Notification::make()
                                ->title(__('Test email sent'))
                                ->body(__('Email sent to :email', ['email' => $data['test_email']]))
                                ->success()
                                ->send();
                        }),
                    Action::make('duplicate')
                        ->label(__('Duplicate'))
                        ->icon(Heroicon::DocumentDuplicate)
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(__('Duplicate Message'))
                        ->modalDescription(__('Create a draft copy of this message?'))
                        ->action(function (Message $record) {
                            // Create duplicate message
                            $duplicate = $record->replicate([
                                'scheduled_at',
                                'sent_at',
                            ]);
                            $duplicate->status = MessageStatus::Draft;
                            $duplicate->subject = $record->subject.' ('.__('Copy').')';
                            $duplicate->save();

                            $record->loadMissing(['tags', 'excludedTags']);

                            if ($record->tags->isNotEmpty()) {
                                $duplicate->tags()->attach($record->tags->pluck('id'));
                            }

                            if ($record->excludedTags->isNotEmpty()) {
                                $duplicate->excludedTags()->attach($record->excludedTags->pluck('id'));
                            }

                            Notification::make()
                                ->title(__('Message duplicated'))
                                ->body(__('New draft created successfully'))
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->visible(fn (Message $record): bool => MessageResource::canDelete($record)),
                ]),
            ]);
    }
}
