<?php

namespace Zrm\Hr\Models;

use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Erpsaas\Core\Models\Company;
use Zrm\Hr\Enums\Hr\SalaryPartBasis;
use Zrm\Hr\Enums\Hr\SalaryPartType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryPart extends Model
{
    use Blamable;
    use CompanyOwned;

    protected $table = 'salary_parts';

    protected $fillable = [
        'part_number',
        'name',
        'type',
        'basis',
        'in_net_salary',
        'amount',
        'debit_account_id',
        'credit_account_id',
        'description',
    ];

    protected $casts = [
        'type' => SalaryPartType::class,
        'basis' => SalaryPartBasis::class,
        'amount' => 'decimal:3',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function getNextSalaryPartNumber(SalaryPartType $type): string
    {
        $lastPart = self::where('type', $type)
            ->orderBy('part_number', 'desc')
            ->first();

        $nextNumber = $lastPart ? (int) $lastPart->part_number + 1 : 1;

        return str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function spss(): HasMany
    {
        return $this->hasMany(SalaryPartSalaryStructure::class);
    }

    public function debitAccount()
    {
        return $this->hasOne(Account::class, 'id', 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->hasOne(Account::class, 'id', 'credit_account_id');
    }
}
