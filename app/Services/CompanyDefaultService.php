<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Setting\CompanyDefault;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyDefaultService
{
    public function createCompanyDefaults(Company $company, User $user, string $currencyCode, string $countryCode, string $language): void
    {
        DB::transaction(function () use ($user, $company, $currencyCode, $countryCode, $language) {
            // Create the company defaults
            $companyDefaultInstance = CompanyDefault::factory()->withDefault($user, $company, $currencyCode, $countryCode, $language);

            // Create Chart of Accounts
            $chartOfAccountsService = app(ChartOfAccountsService::class);
            $chartOfAccountsService->createChartOfAccounts($company, $currencyCode);

            // Get the default bank account; if none exists (fresh DB during tests), create one.
            $defaultBankAccount = $company->bankAccounts()->where('enabled', true)->first();

            if (! $defaultBankAccount) {
                // Create a minimal account and bank account to act as the default.
                $account = \App\Models\Accounting\Account::factory()->create([
                    'company_id' => $company->id,
                    'name' => 'Cash on Hand',
                ]);

                $defaultBankAccount = \App\Models\Banking\BankAccount::factory()->create([
                    'company_id' => $company->id,
                    'account_id' => $account->id,
                    'enabled' => true,
                ]);
            }

            $companyDefaultInstance->state([
                'bank_account_id' => $defaultBankAccount->id,
            ])->createQuietly();
        });
    }
}
