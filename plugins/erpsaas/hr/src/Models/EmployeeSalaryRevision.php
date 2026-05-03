<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryRevision extends Model
{
    use Blamable;
    use CompanyOwned;

    protected $table = 'employee_salary_revisions';

    protected $fillable = [
        'company_id',
        'employee_id',
        'base_salary_amount',
        'effective_from',
        'reason',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'base_salary_amount' => 'decimal:4',
            'effective_from' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
