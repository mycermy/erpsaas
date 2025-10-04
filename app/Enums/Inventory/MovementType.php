<?php

namespace App\Enums\Inventory;

use Filament\Support\Contracts\HasLabel;

enum MovementType: string implements HasLabel
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Return = 'return';
    case Initial = 'initial';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Sale => 'Sale',
            self::Adjustment => 'Adjustment',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::Return => 'Return',
            self::Initial => 'Initial Stock',
        };
    }

    public function isInbound(): bool
    {
        return in_array($this, [
            self::Purchase,
            self::TransferIn,
            self::Return,
            self::Initial,
        ]);
    }

    public function isOutbound(): bool
    {
        return in_array($this, [
            self::Sale,
            self::TransferOut,
        ]);
    }
}
