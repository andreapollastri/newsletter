<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Actions\EmailPreviewAction;
use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EmailPreviewAction::forMessage(),
            EditAction::make()
                ->visible(fn (): bool => MessageResource::canEdit($this->record)),
        ];
    }
}
