<?php

namespace App\Filament\Resources\Messages\Pages;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\MessageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMessage extends EditRecord
{
    protected static string $resource = MessageResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()?->isEditor()) {
            $data['status'] = MessageStatus::Draft->value;
            $data['scheduled_at'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn (): bool => MessageResource::canDelete($this->record)),
        ];
    }
}
