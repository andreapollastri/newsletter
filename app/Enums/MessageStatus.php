<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum MessageStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Sending = 'sending';
    case Sent = 'sent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Ready => 'Pronto',
            self::Sending => 'In invio',
            self::Sent => 'Inviato',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Ready => 'warning',
            self::Sending => 'info',
            self::Sent => 'success',
        };
    }

    public function getIcon(): string|Heroicon|null
    {
        return match ($this) {
            self::Draft => Heroicon::PencilSquare,
            self::Ready => Heroicon::Clock,
            self::Sending => Heroicon::ArrowPath,
            self::Sent => Heroicon::CheckCircle,
        };
    }
}
