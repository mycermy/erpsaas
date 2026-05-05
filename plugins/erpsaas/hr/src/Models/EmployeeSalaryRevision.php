<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Concerns\SearchableEncryption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryRevision extends Model
{
    use Blamable;
    use CompanyOwned;
    use SearchableEncryption;

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

    /**
     * Define which encrypted fields should be searchable via blind indexing
     */
    protected array $searchableEncrypted = [
        'base_salary_amount',
    ];

    protected function casts(): array
    {
        return [
            'base_salary_amount' => 'encrypted:decimal:4',
            'effective_from' => 'date',
        ];
    }

    /**
     * Hidden fields - prevent accidental exposure
     */
    protected $hidden = [
        'base_salary_amount',
        'base_salary_amount_index',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
