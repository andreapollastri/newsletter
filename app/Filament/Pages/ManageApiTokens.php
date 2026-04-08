<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Sanctum\TokenAbility;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Laravel\Sanctum\PersonalAccessToken;

class ManageApiTokens extends Page implements HasTable
{
    use InteractsWithTable;

    /**
     * Plain text token shown in the reveal modal (set immediately before opening that modal).
     * Must be public so Livewire can persist it across the request boundary.
     */
    public string $plainTokenForReveal = '';

    protected static ?string $slug = 'api-tokens';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $title = null;

    public function getTitle(): string
    {
        return __('API tokens');
    }

    public function mount(): void
    {
        $this->bootedInteractsWithTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Personal access tokens'))
            ->description(__('Use these tokens in the Authorization header as Bearer tokens when calling the REST API.'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('abilities')
                    ->label(__('Permissions'))
                    ->formatStateUsing(fn (mixed $state): string => $this->formatTokenAbilitiesLabel($state))
                    ->badge()
                    ->color(fn (mixed $state): string => $this->tokenAbilitiesBadgeColor($state)),

                TextColumn::make('last_used_at')
                    ->label(__('Last used'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('Never')),

                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('createToken')
                    ->label(__('Create token'))
                    ->icon(Heroicon::Plus)
                    ->modalHeading(__('Create API token'))
                    ->modalSubmitActionLabel(__('Create'))
                    ->successNotification(null)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Token name'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder(__('e.g. Mobile app, CI pipeline')),
                        Toggle::make('ability_api')
                            ->label(__('REST API (ability: api)'))
                            ->helperText(__('Access to /api/* endpoints.'))
                            ->default(true),
                        Toggle::make('ability_mcp')
                            ->label(__('MCP server (ability: mcp)'))
                            ->helperText(__('Access to the HTTP MCP endpoint (AI integrations).'))
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        $abilities = array_values(array_filter([
                            ($data['ability_api'] ?? false) ? TokenAbility::Api : null,
                            ($data['ability_mcp'] ?? false) ? TokenAbility::Mcp : null,
                        ]));

                        if ($abilities === []) {
                            Notification::make()
                                ->title(__('Select at least one permission'))
                                ->danger()
                                ->send();

                            return;
                        }

                        $this->plainTokenForReveal = $user->createToken($data['name'], $abilities)->plainTextToken;

                        Notification::make()
                            ->title(__('Token created'))
                            ->success()
                            ->send();

                        $this->replaceMountedAction('showCreatedToken');
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label(__('Revoke'))
                    ->modalHeading(__('Revoke token'))
                    ->modalDescription(__('This token will stop working immediately.')),
            ]);
    }

    /**
     * Standalone action resolved by Filament's {name}Action() convention.
     * Opened via replaceMountedAction() after token creation.
     */
    public function showCreatedTokenAction(): Action
    {
        return Action::make('showCreatedToken')
            ->modalHeading(__('Your new token'))
            ->modalDescription(__('Copy this token now and store it somewhere safe. You will not be able to see it again.'))
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalWidth(Width::TwoExtraLarge)
            ->closeModalByClickingAway(false)
            ->fillForm(fn (): array => [
                'plain_token' => $this->plainTokenForReveal,
            ])
            ->schema([
                Section::make()
                    ->description(__('Use the copy button or select the text. Send it as Authorization: Bearer …'))
                    ->schema([
                        TextInput::make('plain_token')
                            ->label(__('Token'))
                            ->readOnly()
                            ->copyable(
                                copyMessage: __('Copied to clipboard'),
                            )
                            ->extraInputAttributes([
                                'class' => 'font-mono',
                                'spellcheck' => 'false',
                                'style' => 'word-break: break-all',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Done'));
    }

    protected function getTableQuery(): Builder
    {
        return PersonalAccessToken::query()
            ->where('tokenable_id', auth()->id())
            ->where('tokenable_type', User::class)
            ->orderByDesc('created_at');
    }

    public function content(Schema $schema): Schema
    {
        $docsUrl = url('/api/documentation');
        $mcpUrl = url('/mcp/newsletter');

        return $schema
            ->components([
                Section::make(__('API documentation'))
                    ->description(new HtmlString(
                        '<a class="fi-link fi-text-primary-600 hover:fi-underline" href="'.e($docsUrl).'" target="_blank" rel="noopener noreferrer">'.e(__('Open Swagger UI (OpenAPI documentation)')).'</a>'
                    ))
                    ->compact()
                    ->columnSpanFull(),
                Section::make(__('MCP server'))
                    ->description(new HtmlString($this->mcpHelpHtml($mcpUrl)))
                    ->compact()
                    ->columnSpanFull(),
                EmbeddedTable::make(),
            ]);
    }

    /**
     * Sanctum stores abilities as JSON; Filament may pass decoded array or raw JSON string.
     *
     * When the column uses {@see TextColumn::badge()} and the state is a list, Filament formats
     * each ability separately and passes a single ability string (e.g. "api") to formatters — not JSON.
     *
     * @return list<string>|null
     */
    protected function normalizeAbilities(mixed $state): ?array
    {
        if ($state === null) {
            return null;
        }

        if (is_array($state)) {
            /** @var list<string> $state */
            return array_values(array_map(strval(...), $state));
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                /** @var list<string> $decoded */
                return array_values(array_map(strval(...), $decoded));
            }

            return $state !== '' ? [$state] : null;
        }

        return null;
    }

    /**
     * Human-readable label for Sanctum token abilities shown in the table.
     */
    protected function formatTokenAbilitiesLabel(mixed $state): string
    {
        $abilities = $this->normalizeAbilities($state);

        if ($abilities === null || $abilities === []) {
            return '—';
        }

        if (in_array('*', $abilities, true)) {
            return __('All abilities');
        }

        return collect($abilities)
            ->map(fn (string $ability): string => match ($ability) {
                TokenAbility::Api => 'API',
                TokenAbility::Mcp => 'MCP',
                default => $ability,
            })
            ->sort()
            ->values()
            ->implode(' · ');
    }

    /**
     * Filament badge color for one ability cell. With {@see TextColumn::badge()}, this runs per badge
     * so API and MCP keep distinct colors even when both are present on the token.
     */
    protected function tokenAbilitiesBadgeColor(mixed $state): string
    {
        $abilities = $this->normalizeAbilities($state);

        if ($abilities === null || $abilities === []) {
            return 'gray';
        }

        if (in_array('*', $abilities, true)) {
            return 'success';
        }

        return match ($abilities[0] ?? '') {
            TokenAbility::Api => 'info',
            TokenAbility::Mcp => 'warning',
            default => 'gray',
        };
    }

    /**
     * Short help block for connecting AI clients to the HTTP MCP endpoint.
     */
    protected function mcpHelpHtml(string $mcpUrl): string
    {
        $inspector = '<code class="fi-font-mono fi-text-xs">php artisan mcp:inspector mcp/newsletter</code>';

        return '<p class="fi-text-sm fi-text-gray-600 dark:fi-text-gray-400">'
            .e(__('HTTP MCP endpoint (for Cursor, Claude Code, and other MCP clients):'))
            .' <code class="fi-font-mono fi-text-xs">'.e($mcpUrl).'</code>'
            .'</p>'
            .'<p class="fi-mt-2 fi-text-sm fi-text-gray-600 dark:fi-text-gray-400">'
            .e(__('Send the token in the Authorization header as Bearer. The token must include the :ability ability (enable “MCP server” when creating the token).', ['ability' => 'mcp']))
            .'</p>'
            .'<p class="fi-mt-2 fi-text-sm fi-text-gray-600 dark:fi-text-gray-400">'
            .e(__('Debug locally with:'))
            .' '.$inspector
            .'</p>';
    }
}
