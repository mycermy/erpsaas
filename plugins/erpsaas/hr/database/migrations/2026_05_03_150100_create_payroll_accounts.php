<?php

use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Accounts\Models\Accounting\AccountSubtype;
use Erpsaas\Core\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Payroll Statutory Payable and Employee Advances Receivable accounts for all companies
        foreach (Company::all() as $company) {
            $this->ensurePayrollAccount($company);
            $this->ensureAdvancesAccount($company);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Soft delete the accounts if they were created by this migration
        Account::query()
            ->whereIn('name', ['Payroll Statutory Payable', 'Employee Advances Receivable'])
            ->delete();
    }

    private function ensurePayrollAccount(Company $company): Account
    {
        // Check if account already exists
        $existing = Account::query()
            ->where('company_id', $company->id)
            ->where('name', 'Payroll Statutory Payable')
            ->where('archived', false)
            ->first();

        if ($existing) {
            return $existing;
        }

        $subtype = AccountSubtype::query()
            ->where('company_id', $company->id)
            ->where('category', 'liability')
            ->orderBy('id')
            ->first();

        if (! $subtype) {
            throw new \RuntimeException("No liability account subtype found for company [{$company->id}].");
        }

        return Account::create([
            'company_id' => $company->id,
            'subtype_id' => $subtype->id,
            'name' => 'Payroll Statutory Payable',
            'category' => 'liability',
            'code' => '2150',
            'description' => 'Liabilities for employee withholdings and employer statutory contributions (EPF/KWSP, SOCSO, EIS).',
        ]);
    }

    private function ensureAdvancesAccount(Company $company): Account
    {
        // Check if account already exists
        $existing = Account::query()
            ->where('company_id', $company->id)
            ->where('name', 'Employee Advances Receivable')
            ->where('archived', false)
            ->first();

        if ($existing) {
            return $existing;
        }

        $subtype = AccountSubtype::query()
            ->where('company_id', $company->id)
            ->where('category', 'asset')
            ->orderBy('id')
            ->first();

        if (! $subtype) {
            throw new \RuntimeException("No asset account subtype found for company [{$company->id}].");
        }

        return Account::create([
            'company_id' => $company->id,
            'subtype_id' => $subtype->id,
            'name' => 'Employee Advances Receivable',
            'category' => 'asset',
            'code' => '1200',
            'description' => 'Short-term loans given to employees, to be recovered from future salary payments.',
        ]);
    }
};
