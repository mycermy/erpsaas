<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Create a user and a deterministic personal company with USD defaults for tests
        $user = User::factory()->create([
            'name' => 'Test Company Owner',
            'email' => 'test@gmail.com',
            'password' => bcrypt('password'),
        ]);

        // Create personal company and ensure company defaults use USD for tests
        \App\Models\Company::factory()
            ->for($user, 'owner')
            ->withCompanyProfile('US')
            ->withCompanyDefaults('USD')
            ->create([
                'user_id' => $user->id,
                'personal_company' => true,
            ]);

        // Set current company for the user
        $user->current_company_id = 1;
        $user->save();
    }
}
