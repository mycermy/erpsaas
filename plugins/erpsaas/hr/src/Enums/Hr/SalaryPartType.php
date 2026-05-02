<?php

namespace Erpsaas\Hr\Enums\Hr;

use Filament\Support\Contracts\HasLabel;

enum SalaryPartType: string implements HasLabel
{
    case BaseSalary = 'base_salary';
    case Deduction = 'deduction';
    case Addition = 'addition';
    case Reimbursement = 'reimbursement';
    case Bonus = 'bonus';
    case Commission = 'commission';
    case Overtime = 'overtime';
    case EmployerCost = 'employer_cost';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::BaseSalary => 'Base Salary',
            self::Deduction => 'Deduction',
            self::Addition => 'Addition',
            self::Reimbursement => 'Reimbursement',
            self::Bonus => 'Bonus',
            self::Commission => 'Commission',
            self::Overtime => 'Overtime',
            self::EmployerCost => 'Employer Cost',
            self::Other => 'Other',
        };
    }

    public function getPluralLabel(): ?string
    {
        return match ($this) {
            self::BaseSalary => 'Base Salaries',
            self::Deduction => 'Deductions',
            self::Addition => 'Additions',
            self::Reimbursement => 'Reimbursements',
            self::Bonus => 'Bonuses',
            self::Commission => 'Commissions',
            self::Overtime => 'Overtimes',
            self::EmployerCost => 'Employer Costs',
            self::Other => 'Others',
        };
    }

    public function getPrefix(): ?string
    {
        return match ($this) {
            self::BaseSalary => 'BS-',
            self::Deduction => 'DE-',
            self::Addition => 'AD-',
            self::Reimbursement => 'RE-',
            self::Bonus => 'BO-',
            self::Commission => 'CO-',
            self::Overtime => 'OT-',
            self::EmployerCost => 'EC-',
            self::Other => 'VAR-',
        };
    }

    public function getId(): ?int
    {
        return match ($this) {
            self::BaseSalary => 1,
            self::Deduction => 2,
            self::Addition => 3,
            self::Reimbursement => 4,
            self::Bonus => 5,
            self::Commission => 6,
            self::Overtime => 7,
            self::EmployerCost => 8,
            self::Other => 9,
        };
    }
}
