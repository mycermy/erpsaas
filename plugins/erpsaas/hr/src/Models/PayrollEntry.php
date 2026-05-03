<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Enums\Accounting\TransactionType;
use Erpsaas\Core\Models\Common\Offering;
use Erpsaas\Core\Models\Common\Vendor;
use Erpsaas\Hr\Enums\Hr\SalaryPartBasis;
use Erpsaas\Hr\Enums\Hr\SalaryPartType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'bill_id',
        'gross_salary',
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

    /**
     * Create a PayrollEntry linked to a Bill for full dashboard integration.
     *
     * The journal transaction is created manually and linked to the Bill as its
     * initial transaction, so all dashboard widgets that query Bills pick up payroll.
     */
    public static function createWithBill(array $data): self
    {
        $salaryStructure = SalaryStructure::with([
            'spss.salaryPart.debitAccount',
            'spss.salaryPart.creditAccount',
            'payrollLiabilitiesAccount',
        ])->find($data['salary_structure_id']);

        $salaryParts = $salaryStructure->spss->map(fn ($sps) => $sps->salaryPart);

        // Resolve base salary: prefer revision-based, fall back to structure-defined amount
        $baseSalary = static::resolveBaseSalary($data, $salaryParts);

        $netSalary = 0;
        $grossSalary = 0;
        $journalEntries = [];

        foreach ($salaryParts as $part) {
            $amount = $part->basis === SalaryPartBasis::PercentageOfBaseSalary
                ? ($part->amount / 100) * $baseSalary
                : ($part->type === SalaryPartType::BaseSalary ? $baseSalary : (float) $part->amount);

            if ($part->in_net_salary) {
                if ($part->type === SalaryPartType::Deduction) {
                    $netSalary -= $amount;
                } else {
                    $netSalary += $amount;
                    if ($part->type !== SalaryPartType::EmployerCost) {
                        $grossSalary += $amount;
                    }
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
            throw new \Exception("Payroll journal entries do not balance. Debit={$totalDebit}, Credit={$totalCredit}");
        }

        $payrollEntry = self::create(array_merge($data, ['gross_salary' => $grossSalary]));
        $payrollEntry->load('employee.contact');

        $employeeName = $payrollEntry->employee->contact->first_name . ' ' . $payrollEntry->employee->contact->last_name;
        $period = $payrollEntry->from_date . ' to ' . $payrollEntry->to_date;

        // Create the Bill for dashboard/reporting integration
        $vendor = static::getOrCreatePayrollVendor($data['company_id']);

        $bill = Bill::create([
            'company_id' => $data['company_id'],
            'vendor_id' => $vendor->id,
            'bill_number' => 'PAY-' . $payrollEntry->entry_number,
            'date' => $data['from_date'],
            'due_date' => $data['to_date'],
            'status' => BillStatus::Open,
            'currency_code' => 'MYR',
            'subtotal' => (int) ($grossSalary * 100),
            'total' => (int) ($totalDebit),
            'notes' => "Payroll: {$employeeName} ({$period})",
        ]);

        // Create line items using salary offerings for display
        static::createBillLineItems($bill, $salaryParts, $baseSalary, $data['company_id']);

        // Create journal transaction linked to the Bill as its initial transaction
        $transaction = Transaction::create([
            'company_id' => $data['company_id'],
            'type' => TransactionType::Journal,
            'amount' => $totalDebit,
            'posted_at' => $data['from_date'] ?? now(),
            'description' => "Payroll for {$employeeName} for period {$period}",
            'transactionable_type' => Bill::class,
            'transactionable_id' => $bill->id,
        ]);

        foreach ($journalEntries as $entry) {
            $entry['transaction_id'] = $transaction->id;
            $entry['company_id'] = $data['company_id'];
            $transaction->journalEntries()->create($entry);
        }

        $payrollEntry->transaction_id = $transaction->id;
        $payrollEntry->bill_id = $bill->id;
        $payrollEntry->save();

        return $payrollEntry;
    }

    /**
     * Resolve the base salary for payroll calculation.
     *
     * Priority: explicit override > latest salary revision <= payroll period > structure-defined fixed amount
     *
     * @param  array<string, mixed>  $data
     * @param  \Illuminate\Support\Collection<int, SalaryPart>  $salaryParts
     */
    protected static function resolveBaseSalary(array $data, $salaryParts): float
    {
        if (isset($data['base_salary_override'])) {
            return (float) $data['base_salary_override'];
        }

        if (isset($data['employee_id'])) {
            $periodEnd = $data['to_date'] ?? $data['from_date'] ?? now()->toDateString();

            $revision = EmployeeSalaryRevision::query()
                ->where('employee_id', $data['employee_id'])
                ->where('effective_from', '<=', $periodEnd)
                ->orderByDesc('effective_from')
                ->first();

            if ($revision) {
                return (float) $revision->base_salary_amount;
            }
        }

        return (float) $salaryParts->where('type', SalaryPartType::BaseSalary)->sum('amount');
    }

    /**
     * Get or create the single "Payroll Department" vendor for all payroll bills.
     */
    protected static function getOrCreatePayrollVendor(int $companyId): Vendor
    {
        return Vendor::query()
            ->where('company_id', $companyId)
            ->where('name', 'Payroll Department')
            ->firstOr(function () use ($companyId) {
                return Vendor::create([
                    'company_id' => $companyId,
                    'name' => 'Payroll Department',
                    'notes' => 'Internal vendor for payroll salary bills.',
                ]);
            });
    }

    /**
     * Create Bill line items from salary parts and their mapped offerings.
     *
     * @param  \Illuminate\Support\Collection<int, SalaryPart>  $salaryParts
     */
    protected static function createBillLineItems(Bill $bill, $salaryParts, float $baseSalary, int $companyId): void
    {
        $lineNumber = 1;

        foreach ($salaryParts as $part) {
            if (! $part->debitAccount) {
                continue;
            }

            $amount = $part->basis === SalaryPartBasis::PercentageOfBaseSalary
                ? ($part->amount / 100) * $baseSalary
                : ($part->type === SalaryPartType::BaseSalary ? $baseSalary : (float) $part->amount);

            $offering = static::getOrCreateOfferingForPart($part, $companyId);

            $bill->lineItems()->create([
                'company_id' => $companyId,
                'offering_id' => $offering->id,
                'description' => $part->description ?: $part->name,
                'quantity' => 1,
                'unit_price' => (int) ($amount * 100),
                'line_number' => $lineNumber++,
            ]);
        }
    }

    /**
     * Get or create a purchasable Offering that maps to the salary part's debit (expense) account.
     */
    protected static function getOrCreateOfferingForPart(SalaryPart $part, int $companyId): Offering
    {
        $offeringName = static::resolveOfferingNameForPart($part);

        return Offering::query()
            ->where('company_id', $companyId)
            ->where('name', $offeringName)
            ->where('purchasable', true)
            ->firstOr(function () use ($offeringName, $part, $companyId) {
                return Offering::create([
                    'company_id' => $companyId,
                    'name' => $offeringName,
                    'type' => 'service',
                    'purchasable' => true,
                    'sellable' => false,
                    'expense_account_id' => $part->debitAccount->id,
                ]);
            });
    }

    /**
     * Map a SalaryPartType to a canonical offering name for reuse across employees.
     */
    protected static function resolveOfferingNameForPart(SalaryPart $part): string
    {
        $type = $part->type instanceof SalaryPartType ? $part->type : SalaryPartType::from((string) $part->type);

        return match ($type) {
            SalaryPartType::BaseSalary => 'Base Salary',
            SalaryPartType::EmployerCost => 'Employer Statutory Contributions',
            SalaryPartType::Deduction => 'Employee Statutory Deductions',
            SalaryPartType::Addition => 'Salary Additions',
            SalaryPartType::Bonus => 'Employee Bonus',
            SalaryPartType::Overtime => 'Overtime Pay',
            SalaryPartType::Commission => 'Commission',
            SalaryPartType::Reimbursement => 'Reimbursement',
            default => $part->name,
        };
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id', 'id');
    }

    public function transaction(): HasOne
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
