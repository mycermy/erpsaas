<?php

namespace Erpsaas\Hr\Services;

use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Core\Enums\Accounting\BillStatus;
use Erpsaas\Core\Enums\Accounting\TransactionType;
use Erpsaas\Core\Enums\Common\VendorType;
use Erpsaas\Core\Models\Common\Offering;
use Erpsaas\Core\Models\Common\Vendor;
use Erpsaas\Hr\Enums\Hr\SalaryPartBasis;
use Erpsaas\Hr\Enums\Hr\SalaryPartType;
use Erpsaas\Hr\Models\EmployeeAdvance;
use Erpsaas\Hr\Models\EmployeeSalaryRevision;
use Erpsaas\Hr\Models\PayrollEntry;
use Erpsaas\Hr\Models\SalaryPart;
use Erpsaas\Hr\Models\SalaryStructure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PayrollService
{
    /**
     * Create a PayrollEntry linked to a Bill for full dashboard integration.
     * All business logic is wrapped in a database transaction for integrity.
     *
     * @param  array<string, mixed>  $data
     */
    public function createWithBill(array $data): PayrollEntry
    {
        return DB::transaction(function () use ($data) {
            $salaryStructure = SalaryStructure::with([
                'spss.salaryPart.debitAccount',
                'spss.salaryPart.creditAccount',
                'payrollLiabilitiesAccount',
            ])->findOrFail($data['salary_structure_id']);

            /** @var Collection<int, SalaryPart> $salaryParts */
            $salaryParts = $salaryStructure->spss->map(fn($sps) => $sps->salaryPart);

            $baseSalary = $this->resolveBaseSalary($data, $salaryParts);

            [$netSalary, $grossSalary, $journalEntries] = $this->buildJournalLines($salaryParts, $baseSalary);

            $journalEntries[] = [
                'account_id' => $salaryStructure->payrollLiabilitiesAccount->id,
                'type' => 'credit',
                'amount' => $netSalary * 100,
                'description' => 'Net Salary Payable',
            ];

            $this->assertBalanced($journalEntries);

            $totalDebit = (int) collect($journalEntries)->where('type', 'debit')->sum('amount');

            $payrollEntry = PayrollEntry::create(array_merge($data, ['gross_salary' => $grossSalary]));
            $payrollEntry->load('employee.contact');

            $employeeName = $payrollEntry->employee->contact->first_name . ' ' . $payrollEntry->employee->contact->last_name;
            $period = $payrollEntry->from_date . ' to ' . $payrollEntry->to_date;

            $vendor = $this->getOrCreatePayrollVendor($data['company_id']);

            $bill = Bill::create([
                'company_id' => $data['company_id'],
                'vendor_id' => $vendor->id,
                'bill_number' => 'PAY-' . $payrollEntry->entry_number,
                'date' => $data['from_date'],
                'due_date' => $data['to_date'],
                'status' => BillStatus::Open,
                'currency_code' => 'MYR',
                'subtotal' => (int) ($grossSalary * 100),
                'total' => $totalDebit,
                'notes' => "Payroll: {$employeeName} ({$period})",
            ]);

            $this->createBillLineItems($bill, $salaryParts, $baseSalary, $data['company_id']);

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

            $this->processAdvanceRecoveries($data, $payrollEntry, $salaryStructure, $employeeName, $period);

            return $payrollEntry;
        });
    }

    /**
     * Resolve the base salary for a payroll calculation.
     *
     * Priority: explicit override → latest salary revision ≤ period end → structure-defined amount.
     *
     * @param  array<string, mixed>  $data
     * @param  Collection<int, SalaryPart>  $salaryParts
     */
    public function resolveBaseSalary(array $data, Collection $salaryParts): float
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
     * Build journal entry lines for each salary part.
     *
     * Returns [netSalary, grossSalary, journalEntries[]].
     *
     * @param  Collection<int, SalaryPart>  $salaryParts
     * @return array{float, float, array<int, array<string, mixed>>}
     */
    public function buildJournalLines(Collection $salaryParts, float $baseSalary): array
    {
        $netSalary = 0.0;
        $grossSalary = 0.0;
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

            $description = $part->name . ($part->description ? ' (' . $part->description . ')' : '');

            if ($part->debitAccount && $part->creditAccount) {
                $journalEntries[] = ['account_id' => $part->debitAccount->id, 'type' => 'debit', 'amount' => $amount * 100, 'description' => $description];
                $journalEntries[] = ['account_id' => $part->creditAccount->id, 'type' => 'credit', 'amount' => $amount * 100, 'description' => $description];
            } elseif ($part->debitAccount || $part->creditAccount) {
                $account = $part->debitAccount ?? $part->creditAccount;
                $type = $part->debitAccount ? 'debit' : 'credit';
                $journalEntries[] = ['account_id' => $account->id, 'type' => $type, 'amount' => $amount * 100, 'description' => $description];
            }
        }

        return [$netSalary, $grossSalary, $journalEntries];
    }

    /**
     * Build the payslip breakdown for display (read-only, no DB writes).
     *
     * @param  Collection<int, SalaryPart>  $salaryParts
     * @return array{base_salary: float, gross: float, deductions: float, employer_cost: float, net: float, lines: array<int, array<string, mixed>>}
     */
    public function buildPayslipBreakdown(float $baseSalary, Collection $salaryParts): array
    {
        $lines = [];
        $gross = 0.0;
        $deductions = 0.0;
        $employerCost = 0.0;
        $net = 0.0;

        foreach ($salaryParts as $part) {
            $amount = $part->basis === SalaryPartBasis::PercentageOfBaseSalary
                ? ((float) $part->amount / 100) * $baseSalary
                : ($part->type === SalaryPartType::BaseSalary ? $baseSalary : (float) $part->amount);

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
            'base_salary' => round($baseSalary, 2),
            'gross' => round($gross, 2),
            'deductions' => round($deductions, 2),
            'employer_cost' => round($employerCost, 2),
            'net' => round($net, 2),
            'lines' => $lines,
        ];
    }

    /**
     * Process selected advance recoveries for a payroll entry.
     * Creates a balanced recovery journal (DR Payroll Payable / CR Employee Advances Receivable)
     * and marks each advance as recovered.
     *
     * @param  array<string, mixed>  $data
     */
    public function processAdvanceRecoveries(
        array $data,
        PayrollEntry $payrollEntry,
        SalaryStructure $salaryStructure,
        string $employeeName,
        string $period,
    ): void {
        $advanceIds = $data['advance_ids'] ?? [];

        if (empty($advanceIds)) {
            return;
        }

        $advances = EmployeeAdvance::query()
            ->whereIn('id', $advanceIds)
            ->where('employee_id', $data['employee_id'])
            ->whereNull('recovered_at')
            ->whereNotNull('given_at')
            ->get();

        if ($advances->isEmpty()) {
            return;
        }

        $advancesReceivableAccount = Account::query()
            ->where('company_id', $data['company_id'])
            ->where('name', 'Employee Advances Receivable')
            ->where('archived', false)
            ->first();

        $payrollLiabilitiesAccount = $salaryStructure->payrollLiabilitiesAccount;

        if ($advancesReceivableAccount && $payrollLiabilitiesAccount) {
            $totalAmountMinor = $advances->sum(fn(EmployeeAdvance $a) => (int) ($a->amount * 100));

            $recoveryTransaction = Transaction::create([
                'company_id' => $data['company_id'],
                'type' => TransactionType::Journal,
                'amount' => $totalAmountMinor,
                'posted_at' => $data['from_date'] ?? now(),
                'description' => "Advance recovery for {$employeeName} ({$period})",
            ]);

            foreach ($advances as $advance) {
                $amountMinor = (int) ($advance->amount * 100);
                $description = 'Advance recovery: ' . ($advance->reason ?? 'employee advance');

                $recoveryTransaction->journalEntries()->create([
                    'company_id' => $data['company_id'],
                    'account_id' => $payrollLiabilitiesAccount->id,
                    'type' => 'debit',
                    'amount' => $amountMinor,
                    'description' => $description,
                    'transaction_id' => $recoveryTransaction->id,
                ]);

                $recoveryTransaction->journalEntries()->create([
                    'company_id' => $data['company_id'],
                    'account_id' => $advancesReceivableAccount->id,
                    'type' => 'credit',
                    'amount' => $amountMinor,
                    'description' => $description,
                    'transaction_id' => $recoveryTransaction->id,
                ]);
            }
        }

        foreach ($advances as $advance) {
            $advance->recovered_at = now();
            $advance->recovered_from_payroll_id = $payrollEntry->id;
            $advance->save();
        }
    }

    /**
     * Get or create the canonical "Payroll Department" vendor for all payroll bills.
     */
    public function getOrCreatePayrollVendor(int $companyId): Vendor
    {
        return Vendor::query()
            ->where('company_id', $companyId)
            ->where('name', 'Payroll Department')
            ->firstOr(fn() => Vendor::create([
                'company_id' => $companyId,
                'name' => 'Payroll Department',
                'type' => VendorType::Regular,
                'notes' => 'Internal vendor for payroll salary bills.',
            ]));
    }

    /**
     * Create Bill line items from salary parts and their mapped offerings.
     *
     * @param  Collection<int, SalaryPart>  $salaryParts
     */
    public function createBillLineItems(Bill $bill, Collection $salaryParts, float $baseSalary, int $companyId): void
    {
        $lineNumber = 1;

        foreach ($salaryParts as $part) {
            if (! $part->debitAccount) {
                continue;
            }

            $amount = $part->basis === SalaryPartBasis::PercentageOfBaseSalary
                ? ($part->amount / 100) * $baseSalary
                : ($part->type === SalaryPartType::BaseSalary ? $baseSalary : (float) $part->amount);

            $offering = $this->getOrCreateOfferingForPart($part, $companyId);

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
     * Get or create a purchasable Offering mapped to the salary part's expense account.
     */
    public function getOrCreateOfferingForPart(SalaryPart $part, int $companyId): Offering
    {
        $offeringName = $this->resolveOfferingNameForPart($part);

        return Offering::query()
            ->where('company_id', $companyId)
            ->where('name', $offeringName)
            ->where('purchasable', true)
            ->firstOr(fn() => Offering::create([
                'company_id' => $companyId,
                'name' => $offeringName,
                'type' => 'service',
                'purchasable' => true,
                'sellable' => false,
                'expense_account_id' => $part->debitAccount->id,
            ]));
    }

    /**
     * Map a SalaryPartType to a canonical offering name for reuse across employees.
     */
    public function resolveOfferingNameForPart(SalaryPart $part): string
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

    /**
     * Assert that journal entries are balanced (total debits === total credits).
     *
     * @param  array<int, array<string, mixed>>  $journalEntries
     *
     * @throws RuntimeException
     */
    protected function assertBalanced(array $journalEntries): void
    {
        $totalDebit = collect($journalEntries)->where('type', 'debit')->sum('amount');
        $totalCredit = collect($journalEntries)->where('type', 'credit')->sum('amount');

        if ($totalDebit !== $totalCredit) {
            throw new RuntimeException(
                "Payroll journal entries do not balance. Debit={$totalDebit}, Credit={$totalCredit}"
            );
        }
    }
}
