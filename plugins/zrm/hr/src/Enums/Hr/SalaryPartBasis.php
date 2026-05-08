<?php

namespace Zrm\Hr\Enums\Hr;

use Filament\Support\Contracts\HasLabel;

enum SalaryPartBasis: string implements HasLabel
{
    case Fixed = 'fixed';
    case PercentageOfBaseSalary = 'percentage_of_base_salary';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Fixed => 'Fixed',
            self::PercentageOfBaseSalary => 'Percentage of Base Salary',
        };
    }
}
