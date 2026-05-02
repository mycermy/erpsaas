<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Hr\Enums\Hr\SalaryPartBasis;
use Erpsaas\Hr\Enums\Hr\SalaryPartType;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class PayrollEntry extends Model
{
    use Blamable;
    use CompanyOwned;

    protected $table = 'payroll_entries';

    protected $fillable = [
        'entry_number',
        'from_date',
        'to_date',
        'employee_id',
        'salary_structure_id',
        'transaction_id',
    ];

    public static function getNextPayrollEntryNumber(): string
    {
        $year = now()->year;
        $prefix = 'PE' . $year . '-';

        $lastEntry = self::where('entry_number', 'like', $prefix . '%')
            ->orderBy('entry_number', 'desc')
            ->first();

        $nextNumber = $lastEntry
            ? (int) substr($lastEntry->entry_number, strlen($prefix)) + 1
            : 1;

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create a PayrollEntry along with its associated Journal Entries within a transaction.
     */
    public static function createWithTransaction(array $data): self
    {
        $salaryStructure = SalaryStructure::with([
            'spss.salaryPart.debitAccount',
            'spss.salaryPart.creditAccount',
            'payrollLiabilitiesAccount',
        ])->find($data['salary_structure_id']);

        $salaryParts = $salaryStructure->spss->map(function ($sps) {
            return $sps->salaryPart;
        });
        $baseSalary = $salaryParts->where('type', 'base_salary')->sum('amount');
        $netSalary = 0;

        $journalEntries = [];

        foreach ($salaryParts as $part) {
            if ($part->basis == SalaryPartBasis::PercentageOfBaseSalary) {
                $amount = ($part->amount / 100) * $baseSalary;
            } else {
                $amount = $part->amount;
            }

            if ($part->in_net_salary) {
                if (in_array($part->type, [SalaryPartType::Deduction])) {
                    $netSalary -= $amount;
                } else {
                    $netSalary += $amount;
                }
            }

            if ($part->debitAccount && $part->creditAccount) {
                $journalEntries[] = [
                    'account_id' => $part->debitAccount->id,
                    'type' => 'debit',
                    'amount' => $amount * 100,
                    'description' => $part->name . ($part->description ? ' (' . $part->description . ')' : ''),
                ];
                $journalEntries[] = [
                    'account_id' => $part->creditAccount->id,
                    'type' => 'credit',
                    'amount' => $amount * 100,
                    'description' => $part->name . ($part->description ? ' (' . $part->description . ')' : ''),
                ];
            } elseif ($part->debitAccount || $part->creditAccount) {
                $account = $part->debitAccount ?? $part->creditAccount;
                $type = $part->debitAccount ? 'debit' : 'credit';
                $journalEntries[] = [
                    'account_id' => $account->id,
                    'type' => $type,
                    'amount' => $amount * 100,
                    'description' => $part->name . ($part->description ? ' (' . $part->description . ')' : ''),
                ];
            }
        }

        $journalEntries[] = [
            'account_id' => $salaryStructure->payrollLiabilitiesAccount->id,
            'type' => 'credit',
            'amount' => $netSalary * 100,
            'description' => 'Net Salary Payable',
        ];

        $totalDebit = collect($journalEntries)->where('type', 'debit')->sum('amount');
        $totalCredit = collect($journalEntries)->where('type', 'credit')->sum('amount');

        if ($totalDebit !== $totalCredit) {
            throw new \Exception("Error: Journal entries do not balance. Debit = $totalDebit, Credit = $totalCredit");
        }

        $payrollEntry = self::create($data);
        $payrollEntry->load('employee.contact');

        $transaction = Transaction::create([
            'company_id' => $data['company_id'],
            'type' => 'journal',
            'amount' => $totalDebit,
            'posted_at' => $data['from_date'] ?? now(),
            'description' => 'Payroll for ' . $payrollEntry->employee->contact->first_name . ' ' . $payrollEntry->employee->contact->last_name . ' for period ' . $payrollEntry->from_date . ' to ' . $payrollEntry->to_date,
        ]);

        foreach ($journalEntries as $entry) {
            $entry['transaction_id'] = $transaction->id;
            $entry['company_id'] = $data['company_id'];
            $transaction->journalEntries()->create($entry);
        }

        $payrollEntry->transaction_id = $transaction->id;
        $payrollEntry->save();

        return $payrollEntry;
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function salaryStructure()
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id', 'id');
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class, 'id', 'transaction_id');
    }

    public function getPayslipBreakdown(): array
    {
        $salaryStructure = $this->salaryStructure;

        if (! $salaryStructure) {
            throw new RuntimeException('Payroll entry has no salary structure.');
        }

        $parts = $salaryStructure->spss()
            ->with('salaryPart')
            ->get()
            ->pluck('salaryPart')
            ->filter();

        $baseSalary = $parts
            ->where('type', SalaryPartType::BaseSalary)
            ->sum('amount');

        $lines = [];
        $gross = 0.0;
        $deductions = 0.0;
        $employerCost = 0.0;
        $net = 0.0;

        foreach ($parts as $part) {
            $amount = $part->basis === SalaryPartBasis::PercentageOfBaseSalary
                ? ((float) $part->amount / 100) * (float) $baseSalary
                : (float) $part->amount;

            $type = $part->type instanceof SalaryPartType ? $part->type : SalaryPartType::from((string) $part->type);

            if ($part->in_net_salary) {
                if ($type === SalaryPartType::Deduction) {
                    $net -= $amount;
                    $deductions += $amount;
                } else {
                    $net += $amount;
                    $gross += $amount;
                }
            }

            if ($type === SalaryPartType::EmployerCost) {
                $employerCost += $amount;
            }

            $lines[] = [
                'name' => $part->name,
                'type' => $type->value,
                'type_label' => $type->getLabel(),
                'basis' => $part->basis->value,
                'basis_label' => $part->basis->getLabel(),
                'rate' => (float) $part->amount,
                'amount' => round($amount, 2),
                'in_net_salary' => (bool) $part->in_net_salary,
            ];
        }

        return [
            'base_salary' => round((float) $baseSalary, 2),
            'gross' => round($gross, 2),
            'deductions' => round($deductions, 2),
            'employer_cost' => round($employerCost, 2),
            'net' => round($net, 2),
            'lines' => $lines,
        ];
    }
}
