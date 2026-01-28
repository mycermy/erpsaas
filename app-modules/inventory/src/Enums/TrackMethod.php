<?php

namespace Modules\Inventory\Enums;

use Filament\Support\Contracts\HasLabel;

enum TrackMethod: string implements HasLabel
{
    case FIFO = 'fifo';
    case LIFO = 'lifo';
    case Average = 'average';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIFO => 'FIFO (First In, First Out)',
            self::LIFO => 'LIFO (Last In, First Out)',
            self::Average => 'Weighted Average',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::FIFO => 'Oldest inventory is sold first',
            self::LIFO => 'Newest inventory is sold first',
            self::Average => 'Average cost of all inventory',
        };
    }
}
