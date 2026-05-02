<?php

namespace Database\Factories\Common;

use Erpsaas\Accounts\Models\Accounting\Account;
use Erpsaas\Accounts\Models\Accounting\Adjustment;
use Erpsaas\Core\Enums\Accounting\AccountCategory;
use Erpsaas\Core\Enums\Accounting\AccountType;
use Erpsaas\Core\Enums\Accounting\AdjustmentType;
use Erpsaas\Core\Enums\Common\OfferingType;
use Erpsaas\Core\Models\Common\Offering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offering>
 */
class OfferingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Offering::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
            'type' => $this->faker->randomElement(OfferingType::cases()),
            'price' => $this->faker->numberBetween(500, 50000), // $5.00 to $500.00
            'sellable' => false,
            'purchasable' => false,
            'income_account_id' => null,
            'expense_account_id' => null,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    public function withSalesAdjustments(): self
    {
        return $this->afterCreating(function (Offering $offering) {
            $incomeAccount = Account::query()
                ->where('company_id', $offering->company_id)
                ->where('category', AccountCategory::Revenue)
                ->where('type', AccountType::OperatingRevenue)
                ->inRandomOrder()
                ->firstOrFail();

            $offering->updateQuietly([
                'sellable' => true,
                'income_account_id' => $incomeAccount->id,
            ]);

            $adjustments = $offering->company?->adjustments()
                ->where('type', AdjustmentType::Sales)
                ->pluck('id');

            $adjustmentsToAttach = $adjustments->isNotEmpty()
                ? $adjustments->random(min(2, $adjustments->count()))
                : Adjustment::factory()->salesTax()->count(2)->create()->pluck('id');

            $offering->salesAdjustments()->attach($adjustmentsToAttach);
        });
    }

    public function withPurchaseAdjustments(): self
    {
        return $this->afterCreating(function (Offering $offering) {
            $expenseAccount = Account::query()
                ->where('company_id', $offering->company_id)
                ->where('category', AccountCategory::Expense)
                ->where('type', AccountType::OperatingExpense)
                ->inRandomOrder()
                ->firstOrFail();

            $offering->updateQuietly([
                'purchasable' => true,
                'expense_account_id' => $expenseAccount->id,
            ]);

            $adjustments = $offering->company?->adjustments()
                ->where('type', AdjustmentType::Purchase)
                ->pluck('id');

            $adjustmentsToAttach = $adjustments->isNotEmpty()
                ? $adjustments->random(min(2, $adjustments->count()))
                : Adjustment::factory()->purchaseTax()->count(2)->create()->pluck('id');

            $offering->purchaseAdjustments()->attach($adjustmentsToAttach);
        });
    }
}
