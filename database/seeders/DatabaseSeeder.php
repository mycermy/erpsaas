<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserCompanySeeder::class,
            // Other seeders can be added here
            UpdateCompany1DefaultsSeeder::class,
            AttachUserToCompanySeeder::class,
            // should manually run EnhancedInventorySeeder after UserCompanySeeder
            EnhancedInventorySeeder::class,

        ]);
    }
}
