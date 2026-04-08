<?php

namespace App\Filament\Pages\Auth;

use App\Support\UserLocale;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getLocaleFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    protected function getLocaleFormComponent(): Component
    {
        return Select::make('locale')
            ->label(__('Language'))
            ->options([
                'it' => __('Italian'),
                'en' => __('English'),
                'de' => __('German'),
                'fr' => __('French'),
                'es' => __('Spanish'),
                'pt' => __('Portuguese'),
            ])
            ->required()
            ->native(false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $fallback = config('app.locale', UserLocale::default());
        if (! UserLocale::isAllowed($fallback)) {
            $fallback = UserLocale::default();
        }

        if (empty($data['locale']) || ! UserLocale::isAllowed($data['locale'])) {
            $data['locale'] = $fallback;
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return Filament::getCurrentOrDefaultPanel()?->getProfileUrl();
    }
}
