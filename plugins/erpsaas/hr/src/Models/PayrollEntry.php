<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Concerns\SearchableEncryption;
use Erpsaas\Hr\Services\PayrollService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use RuntimeException;

class PayrollEntry extends Model
{
    use Blamable;
    use CompanyOwned;
    use SearchableEncryption;

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

    /**
     * Define which encrypted fields should be searchable via blind indexing
     */
    protected array $searchableEncrypted = [
        'gross_salary',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'gross_salary' => 'encrypted:decimal:4',
        ];
    }

    /**
     * Hidden fields - prevent accidental exposure
     */
    protected $hidden = [
        'gross_salary',
        'gross_salary_index',
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

    public function advances(): HasMany
    {
        return $this->hasMany(EmployeeAdvance::class, 'recovered_from_payroll_id');
    }

    /**
     * Build the payslip breakdown for display purposes.
     *
     * @return array{base_salary: float, gross: float, deductions: float, employer_cost: float, net: float, lines: array<int, array<string, mixed>>}
     */
    public function getPayslipBreakdown(): array
    {
        $salaryStructure = $this->salaryStructure;

        if (! $salaryStructure) {
            throw new RuntimeException('Payroll entry has no salary structure.');
        }

        /** @var Collection<int, SalaryPart> $salaryParts */
        $salaryParts = $salaryStructure->spss()
            ->with('salaryPart')
            ->get()
            ->pluck('salaryPart')
            ->filter();

        $service = app(PayrollService::class);

        $baseSalary = $this->gross_salary
            ? (float) $this->gross_salary
            : $service->resolveBaseSalary([
                'employee_id' => $this->employee_id,
                'from_date' => $this->from_date,
                'to_date' => $this->to_date,
            ], $salaryParts);

        $breakdown = $service->buildPayslipBreakdown($baseSalary, $salaryParts);

        // Add recovered advances to the breakdown
        $advances = $this->advances()->get()->map(fn ($advance) => [
            'amount' => (float) $advance->amount,
            'reason' => $advance->reason,
            'given_at' => $advance->given_at,
        ]);

        $breakdown['advances'] = $advances->toArray();
        $breakdown['total_advances'] = $advances->sum('amount');

        // Subtract advances from net salary
        $breakdown['net'] -= $breakdown['total_advances'];

        return $breakdown;
    }
}
