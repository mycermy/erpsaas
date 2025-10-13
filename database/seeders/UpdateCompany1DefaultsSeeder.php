<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Setting\CompanyDefault;
use App\Models\Setting\Localization;
use Illuminate\Database\Seeder;

class UpdateCompany1DefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::with(['profile', 'profile.address', 'default', 'locale'])->find(1);

        if (! $company) {
            $this->command->info('Company #1 not found, skipping.');

            return;
        }

        $this->command->info('Updating Company #1 defaults...');

        // Update profile address country to MY
        if ($company->profile && $company->profile->address) {
            $company->profile->address->update([
                'country_code' => 'MY',
            ]);
            $this->command->info('Profile address country set to MY');
        }

        // Ensure localization exists and set timezone & language
        $locale = Localization::firstOrNew(['company_id' => $company->id]);
        $locale->timezone = config('app.timezone');
        $locale->language = config('transmatic.source_locale', 'en');
        $locale->saveQuietly();
        $this->command->info('Localization timezone and language updated');

        // Update or create company default to use configured money default
        $currencyCode = config('money.defaults.currency');

        $companyDefault = CompanyDefault::firstOrNew(['company_id' => $company->id]);
        $companyDefault->currency_code = $currencyCode;
        $companyDefault->bank_account_id = $companyDefault->bank_account_id ?? null;
        $companyDefault->created_by = $companyDefault->created_by ?? ($company->owner?->id ?? null);
        $companyDefault->updated_by = $companyDefault->updated_by ?? ($company->owner?->id ?? null);
        $companyDefault->saveQuietly();

        $this->command->info('Company default currency set to ' . $currencyCode);

        // Ensure a Currency record for the company exists and is enabled
        $company->currencies()->firstOrCreate([
            'code' => $currencyCode,
        ], [
            'enabled' => true,
            'created_by' => $company->owner->id ?? null,
            'updated_by' => $company->owner->id ?? null,
        ]);

        $this->command->info('Company currency record ensured');

        $this->command->info('Done.');
    }
}
