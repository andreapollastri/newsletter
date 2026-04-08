<?php

namespace App\Filament\Resources\Messages;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\Pages\CreateMessage;
use App\Filament\Resources\Messages\Pages\EditMessage;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Filament\Resources\Messages\RelationManagers\SendsRelationManager;
use App\Filament\Resources\Messages\Schemas\MessageForm;
use App\Filament\Resources\Messages\Schemas\MessageInfolist;
use App\Filament\Resources\Messages\Tables\MessagesTable;
use App\Models\Message;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getModelLabel(): string
    {
        return __('Message');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Messages');
    }

    public static function getNavigationLabel(): string
    {
        return __('Messages');
    }

    public static function form(Schema $schema): Schema
    {
        return MessageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessagesTable::configure($table);
    }

    /**
     * Shared eager loads for message tables and record pages.
     *
     * @return Builder<Message>
     */
    protected static function messageTableQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'tags',
                'campaign',
            ])
            ->withCount([
                'sends as emails_sent_count' => fn (Builder $query) => $query->whereNotNull('sent_at'),
            ])
            ->withSum([
                'sends as opens_sum' => fn (Builder $query) => $query->whereNotNull('sent_at'),
            ], 'opens_count');
    }

    /**
     * List/search query: hides sent messages whose audience is testing-only.
     * Non-sent testing messages remain visible so they can still be managed.
     *
     * @return Builder<Message>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = static::messageTableQuery();

        if (auth()->user()?->isEditor()) {
            $query->whereNotIn('status', [MessageStatus::Sent, MessageStatus::Sending]);
        }

        static::excludeSentTestingOnly($query);

        return $query;
    }

    /**
     * Resolve edit/view routes for any message (same visibility rules as the list, without global table scopes).
     *
     * @return Builder<Message>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $query = static::messageTableQuery();

        if (auth()->user()?->isEditor()) {
            $query->whereNotIn('status', [MessageStatus::Sent, MessageStatus::Sending]);
        }

        static::excludeSentTestingOnly($query);

        return $query;
    }

    /**
     * Exclude sent messages whose audience consists entirely of testing tags.
     *
     * @param  Builder<Message>  $query
     */
    protected static function excludeSentTestingOnly(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where('status', '!=', MessageStatus::Sent)
                ->orWhereDoesntHave('tags')
                ->orWhereHas('tags', fn (Builder $tq) => $tq->where('is_testing', false));
        });
    }

    public static function getRelations(): array
    {
        return [
            SendsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
            'create' => CreateMessage::route('/create'),
            'view' => ViewMessage::route('/{record}'),
            'edit' => EditMessage::route('/{record}/edit'),
        ];
    }
}
