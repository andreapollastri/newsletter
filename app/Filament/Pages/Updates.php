<?php

namespace App\Filament\Pages;

use App\Updates\UpdateFailed;
use App\Updates\Updater;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * The installed release, the channel, the nightly update, and an update now.
 * The scheduler runs what is asked here (app:update pending); the page polls
 * while an update is asked for or running, and reloads when it is done.
 */
class Updates extends Page
{
    protected static ?string $slug = 'updates';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected string $view = 'filament.pages.updates';

    public function getTitle(): string
    {
        return __('Updates');
    }

    public static function getNavigationLabel(): string
    {
        return __('Updates');
    }

    public function getSubheading(): string
    {
        return __('The release Newsletter runs, the builds it follows and its nightly update.');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdministrator() ?? false;
    }

    protected function updater(): Updater
    {
        return app(Updater::class);
    }

    /** An update is asked for or running: the page polls until it is done. */
    public function isBusy(): bool
    {
        return $this->updater()->running() || $this->updater()->requested();
    }

    /** What the busy banner says. */
    public function busyMessage(): string
    {
        if (! $this->updater()->running()) {
            return __('The update starts within a minute…');
        }

        return (string) ($this->updater()->state()['message'] ?? __('The update is running…'));
    }

    /** Polled while busy: once the update is done, the page reloads on the build now installed. */
    public function followUpdate(): void
    {
        if (! $this->isBusy()) {
            $this->redirect(static::getUrl());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check')
                ->label(__('Check now'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->disabled(fn (): bool => ! $this->updater()->managed())
                ->action(function (): void {
                    try {
                        $this->updater()->check();
                    } catch (UpdateFailed $e) {
                        Notification::make()
                            ->title(__('The repository is not reachable.'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $available = $this->updater()->available();

                    Notification::make()
                        ->title($available
                            ? __(':build is available.', ['build' => $available])
                            : __('Newsletter is on the newest build of its channel.'))
                        ->success()
                        ->send();
                }),

            Action::make('forceUpdate')
                ->label(__('Force update'))
                ->color('gray')
                ->disabled(fn (): bool => ! $this->updater()->managed() || $this->isBusy())
                ->requiresConfirmation()
                ->modalHeading(__('Force update'))
                ->modalDescription(__('Force an update now? Newsletter installs the newest build of its channel even when it is the one already installed, with the same database backup and rollback as every update. It never goes back to an older build.'))
                ->action(fn () => $this->requestUpdate(force: true)),

            Action::make('update')
                ->label(fn (): string => __('Update to :build', ['build' => $this->updater()->available()]))
                ->visible(fn (): bool => $this->updater()->available() !== null)
                ->disabled(fn (): bool => ! $this->updater()->managed() || $this->isBusy())
                ->requiresConfirmation()
                ->modalHeading(fn (): string => __('Update to :build', ['build' => $this->updater()->available()]))
                ->modalDescription(fn (): string => __('Update Newsletter to :build now? It backs up the database, installs and migrates the new build, and puts everything back if a step fails. Newsletter is in maintenance for a moment.', ['build' => $this->updater()->available()]))
                ->action(fn () => $this->requestUpdate()),
        ];
    }

    /**
     * Every value is read when the page renders: the schema is built once per request,
     * before an action changes the state.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make()
                    ->description(fn (): string => config('updates.enabled')
                        ? __('Newsletter updates itself when it is a git checkout, or a release of a deploy (releases/, current, shared/, as Cipi and Deployer make them). This installation is neither: update it the way it was installed.')
                        : __('Updates are off on this installation (a local environment, or APP_UPDATES=false): update it with git.'))
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->color('gray')
                    ->visible(fn (): bool => ! $this->updater()->managed()),

                Callout::make()
                    ->description(__('The scheduler has not run in the last few minutes. Updates asked for here, and the nightly one, start from it: add :cron to the crontab of the user that runs Newsletter.', ['cron' => '* * * * * php artisan schedule:run']))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning')
                    ->visible(fn (): bool => $this->updater()->managed() && ! $this->updater()->schedulerRunning()),

                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Section::make(__('Installed'))
                            ->compact()
                            ->schema([
                                TextEntry::make('version')
                                    ->label(__('Installed'))
                                    ->hiddenLabel()
                                    ->state(fn (): string => $this->updater()->version())
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold),
                                TextEntry::make('build')
                                    ->label(__('Build'))
                                    ->inlineLabel()
                                    ->state(fn (): string => $this->installedBuild())
                                    ->fontFamily(FontFamily::Mono)
                                    ->color('primary')
                                    ->url(fn (): string => $this->updater()->releaseUrl($this->installedBuild()), shouldOpenInNewTab: true),
                            ]),

                        Section::make(fn (): string => $this->updater()->channel() === 'beta' ? __('Newest on main') : __('Newest release'))
                            ->compact()
                            ->afterHeader([
                                Text::make(fn (): string => $this->updater()->available() ? __('update available') : __('up to date'))
                                    ->badge()
                                    ->color(fn (): string => $this->updater()->available() ? 'warning' : 'success')
                                    ->visible(fn (): bool => $this->updater()->available() !== null
                                        || ($this->state('checked_at') !== null && $this->updater()->waiting() === null)),
                            ])
                            ->schema([
                                TextEntry::make('newest')
                                    ->label(fn (): string => $this->updater()->channel() === 'beta' ? __('Newest on main') : __('Newest release'))
                                    ->hiddenLabel()
                                    ->state(fn (): ?string => $this->newestBuild())
                                    ->placeholder('—')
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold)
                                    ->url(fn (): ?string => $this->newestBuild() ? $this->updater()->releaseUrl($this->newestBuild()) : null, shouldOpenInNewTab: true),
                                Text::make(fn (): string => $this->state('checked_at') !== null
                                    ? __('Checked :time', ['time' => Carbon::parse($this->state('checked_at'))->diffForHumans()])
                                    : __('Not checked yet')),
                                Text::make(fn (): ?string => $this->updater()->waiting())
                                    ->visible(fn (): bool => $this->updater()->waiting() !== null),
                            ]),

                        Section::make(__('Automatic updates'))
                            ->key('automatic')
                            ->compact()
                            ->afterHeader([
                                Action::make('toggleAutomatic')
                                    ->label(fn (): string => $this->updater()->automatic() ? __('Turn off') : __('Turn on'))
                                    ->color('gray')
                                    ->size('sm')
                                    ->disabled(fn (): bool => ! $this->updater()->managed())
                                    ->action(function (): void {
                                        $this->updater()->setAutomatic(! $this->updater()->automatic());

                                        Notification::make()
                                            ->title($this->updater()->automatic() ? __('Automatic updates are on.') : __('Automatic updates are off.'))
                                            ->success()
                                            ->send();
                                    }),
                            ])
                            ->schema([
                                TextEntry::make('automatic')
                                    ->label(__('Automatic updates'))
                                    ->hiddenLabel()
                                    ->state(fn (): string => $this->updater()->automatic()
                                        ? __('Every night at :time', ['time' => config('updates.time')])
                                        : __('Off'))
                                    ->size(TextSize::Large)
                                    ->weight(FontWeight::SemiBold),
                                Text::make(__('The nightly run installs the newest build of the channel when there is one.')),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Channel'))
                    ->key('channel')
                    ->description(fn (): string => $this->updater()->channel() === 'beta'
                        ? __('Beta tester mode: Newsletter follows the main branch and installs every new commit on it, before it becomes a release.')
                        : __('Stable: Newsletter installs the release tags (MAJOR.MINOR.PATCH) and nothing in between.'))
                    ->afterHeader([
                        $this->channelAction('stable', __('Stable releases')),
                        $this->channelAction('beta', __('Beta (main branch)')),
                    ])
                    ->schema([
                        Text::make(__('Neither channel goes backwards: back on stable after beta, Newsletter keeps its main build until a release contains it.')),
                    ])
                    ->columnSpanFull(),

                Section::make(__('Last update'))
                    ->afterHeader([
                        Text::make(fn (): string => match ($this->state('status')) {
                            'ok' => __('updated'),
                            'up-to-date' => __('up to date'),
                            'failed' => __('failed'),
                            default => (string) $this->state('status'),
                        })
                            ->badge()
                            ->color(fn (): string => match ($this->state('status')) {
                                'ok', 'up-to-date' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            }),
                        Text::make(__('nightly'))
                            ->visible(fn (): bool => (bool) $this->state('triggered_automatically', false)),
                        Text::make(fn (): ?string => $this->state('finished_at') !== null ? Carbon::parse($this->state('finished_at'))->diffForHumans() : null),
                    ])
                    ->schema([
                        Text::make(fn (): string => (string) $this->state('message', ''))
                            ->color('neutral'),
                        Section::make(__('Log'))
                            ->compact()
                            ->collapsible()
                            ->collapsed(fn (): bool => $this->state('status') !== 'failed')
                            ->visible(fn (): bool => ! empty($this->state('log')))
                            ->schema([
                                Html::make(fn (): HtmlString => new HtmlString(
                                    '<pre style="max-height: 24rem; overflow: auto; margin: 0; padding: 0.75rem; border-radius: 0.5rem; background: var(--gray-950); color: var(--gray-100); font-family: var(--mono-font-family), ui-monospace, monospace; font-size: 11px; line-height: 1.6; white-space: pre-wrap;">'
                                    .e(implode("\n", (array) $this->state('log', [])))
                                    .'</pre>'
                                )),
                            ]),
                        Text::make(__('The previous build and its database are back in place. The full log is in storage/app/updates/update.log.'))
                            ->visible(fn (): bool => $this->state('status') === 'failed'),
                    ])
                    ->visible(fn (): bool => ! in_array($this->state('status'), [null, 'running'], true))
                    ->columnSpanFull(),

                Text::make(__('Each update backs up the database, installs the new build (in place for a git checkout, next to the running release for a deploy), installs its dependencies, migrates and checks that Newsletter boots; if a step fails, the previous build and the database come back.'))
                    ->columnSpanFull(),
            ]);
    }

    /** One half of the channel switch: the channel in use is the highlighted one. */
    protected function channelAction(string $channel, string $label): Action
    {
        return Action::make($channel.'Channel')
            ->label($label)
            ->color(fn (): string => $this->updater()->channel() === $channel ? 'primary' : 'gray')
            ->size('sm')
            ->disabled(fn (): bool => ! $this->updater()->managed() || $this->isBusy())
            ->action(function () use ($channel): void {
                $this->updater()->setChannel($channel);

                Notification::make()
                    ->title($channel === 'beta' ? __('Newsletter now follows the main branch.') : __('Newsletter now follows the release tags.'))
                    ->success()
                    ->send();
            });
    }

    /** A value the last check or run recorded. */
    protected function state(string $key, mixed $default = null): mixed
    {
        return $this->updater()->state()[$key] ?? $default;
    }

    /** The installed build, as the last check named it. */
    protected function installedBuild(): string
    {
        return $this->state('current') ?? $this->updater()->installed();
    }

    /** The newest build of the channel the last check saw. */
    protected function newestBuild(): ?string
    {
        return $this->state($this->updater()->channel() === 'beta' ? 'main' : 'latest');
    }

    /** Ask the scheduler for an update; it starts within a minute. */
    protected function requestUpdate(bool $force = false): void
    {
        if ($this->isBusy()) {
            Notification::make()
                ->title(__('An update is already under way.'))
                ->danger()
                ->send();

            return;
        }

        $this->updater()->request($force, auth()->user()?->email);

        Notification::make()
            ->title(__('The update starts within a minute.'))
            ->success()
            ->send();
    }
}
