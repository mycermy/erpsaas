<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Seeder;

class UserCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a single admin user and their personal company if not already present
        $email = 'admin@erpsaas.com';

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::factory()
                ->withPersonalCompany(function (CompanyFactory $factory) {
                    return $factory
                        ->state([
                            'name' => 'ERPSAAS',
                        ])
                        // ->withTransactions(250)
                        // ->withOfferings()
                        // ->withInvoices(30)
                        // ->withRecurringInvoices()
                        // ->withEstimates(30)
                        // ->withBills(30)
                        ->withClients()
                        ->withVendors();
                })
                ->create([
                    'name' => 'Admin',
                    'email' => $email,
                    'password' => bcrypt('password'),
                    'current_company_id' => 1,  // Assuming this will be the ID of the created company
                ]);

            // Ensure user is attached to their personal company
            $company = Company::find($user->current_company_id);
            if ($company && ! $user->companies()->where('company_id', $company->id)->exists()) {
                $user->companies()->attach($company->id, [
                    'role' => 'admin',
                ]);
                $this->command->info("✓ Attached user '{$user->name}' to company '{$company->name}' as admin");
            }
        } else {
            $this->command->info("User '{$user->name}' already exists");

            // Verify user is attached to their current company
            if ($user->current_company_id) {
                $company = Company::find($user->current_company_id);
                if ($company && ! $user->companies()->where('company_id', $company->id)->exists()) {
                    $user->companies()->attach($company->id, [
                        'role' => 'admin',
                    ]);
                    $this->command->info("✓ Attached existing user to company '{$company->name}'");
                }
            }
        }

        // $additionalCompanies = [
        //     ['name' => 'British Crown Analytics', 'country' => 'GB', 'currency' => 'GBP', 'locale' => 'en'],
        //     ['name' => 'Berlin Tech Solutions', 'country' => 'DE', 'currency' => 'EUR', 'locale' => 'en'],
        //     ['name' => 'Mumbai Software Services', 'country' => 'IN', 'currency' => 'INR', 'locale' => 'en'],
        // ];

        // foreach ($additionalCompanies as $companyData) {
        //     Company::factory()
        //         ->state([
        //             'name' => $companyData['name'],
        //             'user_id' => $user->id,
        //             'personal_company' => false,
        //         ])
        //         ->withCompanyProfile($companyData['country'])
        //         ->withCompanyDefaults($companyData['currency'], $companyData['locale'])
        //         ->withTransactions(50)
        //         ->withOfferings()
        //         ->withClients()
        //         ->withVendors()
        //         ->withInvoices()
        //         ->withRecurringInvoices()
        //         ->withEstimates()
        //         ->withBills()
        //         ->create();
        // }
    }
}
