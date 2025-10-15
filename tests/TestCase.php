<?php

namespace Tests;

use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\User;
use App\Testing\TestsReport;
use Database\Seeders\TestDatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Features\SupportTesting\Testable;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     */
    protected bool $seed = true;

    /**
     * Run a specific seeder before each test.
     */
    protected string $seeder = TestDatabaseSeeder::class;

    protected User $testUser;

    protected ?Company $testCompany;

    protected function setUp(): void
    {
        parent::setUp();

        Testable::mixin(new TestsReport);

        $this->testUser = User::first();

        $this->testCompany = $this->testUser->ownedCompanies->first();

        // Ensure company defaults and default bank account exist for tests
        try {
            $default = $this->testCompany->default; // trigger lazy creation if missing
            if ($default && ! $default->bankAccount) {
                // Create a bank account and attach if missing
                $account = \App\Models\Accounting\Account::factory()->create(['company_id' => $this->testCompany->id, 'name' => 'Cash on Hand']);
                $bankAccount = \App\Models\Banking\BankAccount::factory()->create(['company_id' => $this->testCompany->id, 'account_id' => $account->id, 'enabled' => true]);
                $default->bank_account_id = $bankAccount->id;
                $default->save();
                $this->testCompany->load('default');
            }
        } catch (\Throwable $e) {
            // If something goes wrong here, continue; tests may still fail and will show a clear trace.
        }

        $this->testUser->switchCompany($this->testCompany);

        $this->actingAs($this->testUser);

        Filament::setTenant($this->testCompany);
    }

    public function withOfferings(): static
    {
        Offering::factory()
            ->for($this->testCompany)
            ->withSalesAdjustments()
            ->withPurchaseAdjustments()
            ->create();

        return $this;
    }
}
