<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class AttachUserToCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Attaching users to companies...');

        $user = User::first();
        $company = Company::first();

        if (! $user) {
            $this->command->error('No user found in database.');

            return;
        }

        if (! $company) {
            $this->command->error('No company found in database.');

            return;
        }

        // Check if user is already attached to the company
        if ($user->companies()->where('company_id', $company->id)->exists()) {
            $this->command->info("User '{$user->name}' is already attached to company '{$company->name}'");

            return;
        }

        // Attach user to company with admin role
        $user->companies()->attach($company->id, [
            'role' => 'admin',
        ]);

        // Set as current company if not set
        if (! $user->current_company_id) {
            $user->update(['current_company_id' => $company->id]);
        }

        $this->command->info("✓ Attached user '{$user->name}' (ID: {$user->id}) to company '{$company->name}' (ID: {$company->id}) as admin");
        $this->command->info("✓ Set company '{$company->name}' as user's current company");
    }
}
