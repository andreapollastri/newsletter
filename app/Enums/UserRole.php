<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case Editor = 'editor';
    case Manager = 'manager';
    case Administrator = 'administrator';

    public function getLabel(): string
    {
        return match ($this) {
            self::Editor => __('Editor'),
            self::Manager => __('Manager'),
            self::Administrator => __('Administrator'),
        };
    }
}
