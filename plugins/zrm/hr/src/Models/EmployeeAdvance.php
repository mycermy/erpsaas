<?php

namespace Zrm\Hr\Models;

use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvance extends Model
{
    use Blamable;
    use CompanyOwned;

    protected $table = 'employee_advances';

    protected $fillable = [
        'company_id',
        'employee_id',
        'amount',
        'given_at',
        'recovered_at',
        'recovered_from_payroll_id',
        'reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'given_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function recoveredFromPayroll(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'recovered_from_payroll_id');
    }

    /**
     * Check if this advance has been recovered (repaid).
     */
    public function isRecovered(): bool
    {
        return $this->recovered_at !== null;
    }

    /**
     * Get the outstanding (unrecovered) amount.
     */
    public function getOutstandingAmount(): float
    {
        return $this->isRecovered() ? 0.0 : (float) $this->amount;
    }
}
