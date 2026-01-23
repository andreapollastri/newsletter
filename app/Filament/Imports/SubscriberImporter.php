<?php

namespace App\Filament\Imports;

use App\Enums\SubscriberStatus;
use App\Models\Subscriber;
use App\Models\Tag;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class SubscriberImporter extends Importer
{
    protected static ?string $model = Subscriber::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('email')
                ->requiredMapping()
                ->rules(['required', 'email', 'max:255']),

            ImportColumn::make('name')
                ->guess(['nome', 'full_name', 'fullname', 'nome_completo'])
                ->rules(['nullable', 'max:255']),

            ImportColumn::make('tags')
                ->guess(['tag', 'etichette', 'categorie'])
                ->fillRecordUsing(function (Subscriber $record, ?string $state) {
                    // Tags will be handled in afterSave
                })
                ->rules(['nullable']),
        ];
    }

    public function resolveRecord(): ?Subscriber
    {
        // Find existing subscriber by email or create new
        return Subscriber::firstOrNew([
            'email' => $this->data['email'],
        ]);
    }

    protected function beforeSave(): void
    {
        // Set default status for new subscribers
        if (! $this->record->exists) {
            $this->record->status = SubscriberStatus::Confirmed;
            $this->record->confirmed_at = now();
        }
    }

    protected function afterSave(): void
    {
        // Handle tags
        if (isset($this->data['tags']) && $this->data['tags']) {
            $tagNames = array_map('trim', explode(',', $this->data['tags']));
            $tagIds = [];

            foreach ($tagNames as $tagName) {
                if (! empty($tagName)) {
                    $tag = Tag::firstOrCreate(['name' => $tagName]);
                    $tagIds[] = $tag->id;
                }
            }

            $this->record->tags()->sync($tagIds);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('Subscriber import completed: :count row(s) imported.', [
            'count' => Number::format($import->successful_rows),
        ]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.__(':count row(s) failed.', [
                'count' => Number::format($failedRowsCount),
            ]);
        }

        return $body;
    }
}
