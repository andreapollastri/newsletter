<?php

namespace App\Filament\Actions;

use App\Models\Message;
use App\Models\Template;
use App\Support\NewsletterHtml;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;

/**
 * Opens a modal that renders email HTML in a sandboxed iframe (no scripts, no navigation, no same-origin access).
 */
class EmailPreviewAction extends Action
{
    protected Closure|string|null $previewHtml = null;

    public static function getDefaultName(): ?string
    {
        return 'preview';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('Preview'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalContent(fn (EmailPreviewAction $action): View => view('filament.components.email-preview', [
                'html' => $action->getPreviewHtml(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalWidth(Width::FiveExtraLarge);
    }

    /**
     * Full email for a message (template + body), personalised for the signed-in user.
     */
    public static function forMessage(): static
    {
        return static::make()
            ->modalHeading(fn (Message $record): string => $record->subject)
            ->modalDescription(__('Saved version, rendered with its template. Tracking links are added only when sending.'))
            ->html(fn (Message $record): string => NewsletterHtml::preview(
                $record,
                (string) auth()->user()?->name,
                (string) auth()->user()?->email,
            ));
    }

    /**
     * Template filled with sample content in place of {{body}}.
     */
    public static function forTemplate(): static
    {
        return static::make()
            ->modalHeading(fn (Template $record): string => $record->name)
            ->modalDescription(__('Saved version, filled with sample content in place of {{body}}.'))
            ->html(fn (Template $record): string => NewsletterHtml::fillPlaceholders(
                NewsletterHtml::absolutizeImageSources(
                    NewsletterHtml::mergeIntoTemplate($record->html_content, NewsletterHtml::SAMPLE_BODY),
                ),
                (string) auth()->user()?->name,
                (string) auth()->user()?->email,
                '#',
            ));
    }

    public function html(Closure|string $html): static
    {
        $this->previewHtml = $html;

        return $this;
    }

    public function getPreviewHtml(): string
    {
        return (string) $this->evaluate($this->previewHtml);
    }
}
