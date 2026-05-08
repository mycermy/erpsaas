<?php

namespace Zrm\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SalaryPartSalaryStructure extends Pivot
{
    protected $table = 'salary_part_salary_structures';

    public $incrementing = true;

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function salaryPart(): BelongsTo
    {
        return $this->belongsTo(SalaryPart::class);
    }
}
