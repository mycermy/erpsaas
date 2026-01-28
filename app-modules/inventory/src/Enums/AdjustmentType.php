<?php

namespace Modules\Inventory\Enums;

enum AdjustmentType: string
{
    case Stocktake = 'stocktake';
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::Stocktake => 'Stocktake',
            self::Damage => 'Damage/Loss',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Stocktake => 'Physical count adjustment - automatically uses FIFO for batch consumption',
            self::Damage => 'Damage or loss adjustment - manually select batches to consume',
        };
    }
}