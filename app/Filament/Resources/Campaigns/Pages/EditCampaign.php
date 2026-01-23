<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn () => $this->record->messages()->exists())
                ->tooltip(fn () => $this->record->messages()->exists()
                    ? __('Cannot delete campaign with associated messages')
                    : null),
            ForceDeleteAction::make()
                ->disabled(fn () => $this->record->messages()->exists())
                ->tooltip(fn () => $this->record->messages()->exists()
                    ? __('Cannot delete campaign with associated messages')
                    : null),
            RestoreAction::make(),
        ];
    }
}
