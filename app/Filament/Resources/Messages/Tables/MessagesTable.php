<?php

namespace App\Filament\Resources\Messages\Tables;

use App\Enums\MessageStatus;
use App\Enums\SubscriberStatus;
use App\Jobs\SendNewsletterEmail;
use App\Models\Message;
use App\Models\MessageSend;
use App\Models\Subscriber;
use App\Models\User;
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
                    ->label(__('Status'))
                    ->options(MessageStatus::class),

                SelectFilter::make('campaign')
                    ->label(__('Campaign'))
                    ->relationship('campaign', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (Message $record) => $record->status === MessageStatus::Sent),
                    EditAction::make()
                        ->visible(fn (Message $record) => $record->status !== MessageStatus::Sent),
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

                            // Send database notification to all users
                            foreach (User::all() as $user) {
                                Notification::make()
                                    ->title(__('Sending started'))
                                    ->body(__('Message ":subject" is being sent to :count recipients.', [
                                        'subject' => $record->subject,
                                        'count' => $subscribers->count(),
                                    ]))
                                    ->success()
                                    ->sendToDatabase($user);
                            }
                        }),
                    Action::make('sendTest')
                        ->label(__('Send Test'))
                        ->icon(Heroicon::Beaker)
                        ->color('warning')
                        ->visible(fn (Message $record) => $record->status !== MessageStatus::Sent)
                        ->form([
                            TextInput::make('test_email')
                                ->email()
                                ->required()
                                ->placeholder('test@example.com')
                                ->label(__('Test Email')),
                        ])
                        ->action(function (Message $record, array $data) {
                            // Load template relationship if not already loaded
                            $record->loadMissing('template');

                            // Build the complete HTML with template
                            $htmlContent = self::buildTestHtmlContent($record);

                            // Convert relative URLs to absolute
                            $htmlContent = self::convertToAbsoluteUrls($htmlContent);

                            // Replace placeholders with test data
                            $subject = self::replaceTestPlaceholders($record->subject);
                            $htmlContent = self::replaceTestPlaceholders($htmlContent);

                            // Send test email directly without tracking
                            Mail::html($htmlContent, function ($message) use ($subject, $data) {
                                $message->to($data['test_email'])
                                    ->subject('[TEST] '.$subject);
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

                            // Copy tag relationships
                            if ($record->tags->isNotEmpty()) {
                                $duplicate->tags()->attach($record->tags->pluck('id'));
                            }

                            Notification::make()
                                ->title(__('Message duplicated'))
                                ->body(__('New draft created successfully'))
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make()
                        ->visible(fn (Message $record) => $record->status !== MessageStatus::Sent && $record->status !== MessageStatus::Sending),
                ]),
            ]);
    }

    /**
     * Build the complete HTML content with template for test emails.
     */
    protected static function buildTestHtmlContent(Message $message): string
    {
        $messageBody = $message->html_content;

        // If there's a template, merge the body into it
        if ($message->template) {
            $templateHtml = $message->template->html_content;

            // Replace {{body}} placeholder with message content
            if (str_contains($templateHtml, '{{body}}')) {
                return str_replace('{{body}}', $messageBody, $templateHtml);
            }

            // If no {{body}} placeholder, append message to template
            return $templateHtml.$messageBody;
        }

        // No template, return message body wrapped in basic HTML
        return $messageBody;
    }

    /**
     * Convert relative URLs to absolute URLs for images.
     */
    protected static function convertToAbsoluteUrls(string $content): string
    {
        $baseUrl = config('app.url');

        // Convert relative src attributes to absolute
        $content = preg_replace_callback(
            '/src=["\'](?!https?:\/\/)([^"\']+)["\']/i',
            function ($matches) use ($baseUrl) {
                $path = ltrim($matches[1], '/');

                return 'src="'.$baseUrl.'/storage/'.$path.'"';
            },
            $content
        );

        return $content;
    }

    /**
     * Replace placeholders with test data.
     */
    protected static function replaceTestPlaceholders(string $content): string
    {
        return str_replace(
            ['{{name}}', '{{email}}', '{{unsubscribe_url}}'],
            ['NAME', 'EMAIL', '#'],
            $content
        );
    }
}
