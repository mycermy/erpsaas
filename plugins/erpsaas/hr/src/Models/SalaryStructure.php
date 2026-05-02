<?php

namespace Erpsaas\Hr\Models;

use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryStructure extends Model
{
    use Blamable;
    use CompanyOwned;

    protected $table = 'salary_structures';

    protected $fillable = [
        'name',
        'description',
        'account_id',
        'effective_date',
        'termination_date',
    ];

    public function spss(): HasMany
    {
        return $this->hasMany(SalaryPartSalaryStructure::class);
    }

    public function payrollLiabilitiesAccount()
    {
        return $this->hasOne(Account::class, 'id', 'account_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
