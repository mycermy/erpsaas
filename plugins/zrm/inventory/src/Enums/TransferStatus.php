<?php

namespace Zrm\Inventory\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransferStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InTransit => 'In Transit',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InTransit => 'info',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }
}
