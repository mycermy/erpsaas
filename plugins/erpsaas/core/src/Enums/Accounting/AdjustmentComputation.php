<?php

namespace Erpsaas\Core\Enums\Accounting;

use Erpsaas\Core\Enums\Concerns\ParsesEnum;
use Filament\Support\Contracts\HasLabel;

enum AdjustmentComputation: string implements HasLabel
{
    use ParsesEnum;

    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function getLabel(): ?string
    {
        return translate($this->name);
    }

    public function isPercentage(): bool
    {
        return $this == self::Percentage;
    }

    public function isFixed(): bool
    {
        return $this == self::Fixed;
    }
}
