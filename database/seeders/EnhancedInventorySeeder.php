<?php

namespace Database\Seeders;

use App\Enums\Accounting\BillStatus;
use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Inventory\AdjustmentStatus;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Common\Client;
use App\Models\Common\Offering;
use App\Models\Common\Vendor;
use App\Models\Company;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentItem;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class EnhancedInventorySeeder extends Seeder
{
    private $faker;

    private $company;

    private $inventoryService;

    public function run(): void
    {
        $this->faker = Faker::create();
        $this->company = Company::first();

        if (! $this->company) {
            $this->command->error('No company found. Run CompanySeeder first.');

            return;
        }

        $this->inventoryService = app(InventoryService::class);

        $this->command->info('Creating offerings and inventory items...');
        $offerings = $this->createStockableOfferings();

        $this->command->info('Creating warehouses...');
        $warehouse = $this->createWarehouse();

        $this->command->info('Getting vendors and clients...');
        $vendors = Vendor::where('company_id', $this->company->id)->get();
        $clients = Client::where('company_id', $this->company->id)->get();

        if ($vendors->isEmpty() || $clients->isEmpty()) {
            $this->command->warn('No vendors or clients found. Please run UserCompanySeeder first.');
            $this->command->info('Creating minimal vendors and clients for testing...');
            $vendors = $this->createVendors();
            $clients = $this->createClients();
        } else {
            $this->command->info("  Found {$vendors->count()} vendors and {$clients->count()} clients");
        }

        $this->command->info('Creating initial stock adjustments...');
        $this->createInitialStock($offerings, $warehouse);

        $this->command->info('Creating purchase bills...');
        $this->createPurchaseBills($offerings, $vendors->all());

        $this->command->info('Creating sales invoices...');
        $this->createSalesInvoices($offerings, $clients->all());

        $this->command->info('Creating damage adjustments...');
        $this->createDamageAdjustments($offerings, $warehouse);

        $this->command->info('Enhanced inventory seeding completed!');
    }

    private function createStockableOfferings(): array
    {
        $offerings = [];

        // Get accounts from the company's chart of accounts
        // Use nullable chaining and provide fallback IDs
        $incomeAccount = $this->company->accounts()->where('type', 'operating_revenue')->where('name', 'Product Sales')->first();
        $inventoryAccount = $this->company->accounts()->where('type', 'current_asset')->where('name', 'Inventory')->first();
        $expenseAccount = $this->company->accounts()->where('type', 'operating_expense')->where('name', 'Cost of Goods Sold')->first();

        if (! $incomeAccount || ! $inventoryAccount || ! $expenseAccount) {
            $this->command->warn('  Required accounts not found. Trying to find any matching accounts...');
            $incomeAccount = $incomeAccount ?? $this->company->accounts()->where('type', 'operating_revenue')->first();
            $inventoryAccount = $inventoryAccount ?? $this->company->accounts()->where('type', 'current_asset')->first();
            $expenseAccount = $expenseAccount ?? $this->company->accounts()->where('type', 'operating_expense')->first();
        }

        if (! $incomeAccount || ! $inventoryAccount || ! $expenseAccount) {
            $this->command->error('  Critical: Cannot find required accounts. Please seed accounts first.');
            $this->command->info('  Run: php artisan db:seed --class=AccountSeeder');

            return [];
        }

        $incomeAccountId = $incomeAccount->id;
        $inventoryAccountId = $inventoryAccount->id;
        $expenseAccountId = $expenseAccount->id;

        // Use same products as InventorySeeder for easy comparison
        $products = [
            [
                'name' => 'Laptop Computer',
                'type' => 'product',
                'sku' => 'COMP-LAP-001',
                'track_method' => \App\Enums\Inventory\TrackMethod::FIFO,
                'description' => 'High-performance laptop computer',
                'price' => 150000, // MYR 1500
                'unit_cost' => 100000, // MYR 1000
                'asset_account_id' => $inventoryAccountId,
            ],
            [
                'name' => 'Wireless Mouse',
                'type' => 'product',
                'sku' => 'ACCS-MOU-001',
                'track_method' => \App\Enums\Inventory\TrackMethod::FIFO,
                'description' => 'Ergonomic wireless mouse',
                'price' => 5000, // MYR 50
                'unit_cost' => 3000, // MYR 30
                'asset_account_id' => $inventoryAccountId,

            ],
            [
                'name' => 'Monitor 27"',
                'type' => 'product',
                'sku' => 'DISP-MON-027',
                'track_method' => \App\Enums\Inventory\TrackMethod::LIFO,
                'description' => '27-inch 4K monitor',
                'price' => 80000, // MYR 800
                'unit_cost' => 60000, // MYR 600
                'asset_account_id' => $inventoryAccountId,
            ],
            [
                'name' => 'Keyboard Mechanical',
                'type' => 'product',
                'sku' => 'ACCS-KEY-001',
                'track_method' => \App\Enums\Inventory\TrackMethod::FIFO,
                'description' => 'Mechanical keyboard with RGB',
                'price' => 30000, // MYR 300
                'unit_cost' => 20000, // MYR 200
                'asset_account_id' => $inventoryAccountId,
            ],
        ];

        foreach ($products as $productData) {
            // Create or find offering
            $offering = Offering::updateOrCreate(
                [
                    'company_id' => $this->company->id,
                    'name' => $productData['name'],
                ],
                [
                    'type' => $productData['type'],
                    'description' => $productData['description'],
                    'price' => $productData['price'],
                    'sellable' => true,
                    'purchasable' => true,
                    'stockable' => true,
                    'income_account_id' => $incomeAccountId,
                    'expense_account_id' => $expenseAccountId,
                ]
            );

            // Create associated inventory item (this makes it stockable)
            $inventoryItem = InventoryItem::updateOrCreate(
                [
                    'company_id' => $this->company->id,
                    'offering_id' => $offering->id,
                ],
                [
                    'sku' => $productData['sku'],
                    'track_method' => $productData['track_method'],
                    'asset_account_id' => $productData['asset_account_id'],
                    'active' => true,
                    'track_batches' => true,
                    'reorder_level' => $this->faker->numberBetween(5, 20),
                    'reorder_quantity' => $this->faker->numberBetween(20, 50),
                    'created_by' => 1,
                ]
            );

            $offerings[] = [
                'offering' => $offering,
                'inventoryItem' => $inventoryItem,
                'unit_cost' => $productData['unit_cost'], // Store unit cost for reference
            ];

            $this->command->info("  Created/Found: {$offering->name} (SKU: {$inventoryItem->sku})");
        }

        return $offerings;
    }

    private function createWarehouse(): Warehouse
    {
        $warehouse = Warehouse::firstOrCreate(
            [
                'company_id' => $this->company->id,
                'code' => 'MAIN',
            ],
            [
                'name' => 'Main Warehouse',
                'code' => 'MAIN',
                'address' => 'Sebelah MyDin Bertam',
                'city' => 'Bandar Bertam Putra',
                'state' => 'Pulau Pinang',
                'postal_code' => '13200',
                'country' => 'MYS',
                'contact_name' => 'John Manager',
                'contact_phone' => '+1-555-0100',
                'contact_email' => 'main@warehouse.test',
                'is_default' => true,
                'active' => true,
                'created_by' => 1,
            ]
        );

        $this->command->info("  Created/Found warehouse: {$warehouse->name}");

        return $warehouse;
    }

    private function createVendors(): array
    {
        $vendors = [];

        for ($i = 1; $i <= 3; $i++) {
            $vendors[] = Vendor::factory()->create([
                'company_id' => $this->company->id,
                'name' => "Supplier {$i}",
            ]);
        }

        $this->command->info('  Created 3 vendors');

        return $vendors;
    }

    private function createClients(): array
    {
        $clients = [];

        for ($i = 1; $i <= 3; $i++) {
            $clients[] = Client::factory()->create([
                'company_id' => $this->company->id,
                'name' => "Customer {$i}",
            ]);
        }

        $this->command->info('  Created 3 clients');

        return $clients;
    }

    private function createInitialStock(array $offerings, Warehouse $warehouse): void
    {
        $now = now();

        // Set initial stock date to 120 days ago (oldest transaction)
        $adjustmentDate = $now->copy()->subDays(120);

        foreach ($offerings as $index => $offeringData) {
            $inventoryItem = $offeringData['inventoryItem'];
            $baseUnitCost = $offeringData['unit_cost'];

            // Each item gets same date (all initial stock on same day)
            $quantity = $this->faker->numberBetween(50, 200);

            // Create adjustment as Draft first
            $adjustment = InventoryAdjustment::create([
                'company_id' => $this->company->id,
                'warehouse_id' => $warehouse->id,
                'adjustment_number' => 'INIT-' . $inventoryItem->sku . '-' . $adjustmentDate->format('Ymd'),
                'adjustment_date' => $adjustmentDate,
                'adjustment_type' => \App\Enums\Inventory\AdjustmentType::Stocktake,
                'status' => AdjustmentStatus::Draft,
                'reason' => 'Initial stock',
            ]);

            InventoryAdjustmentItem::create([
                'company_id' => $this->company->id, // Explicitly set company_id
                'adjustment_id' => $adjustment->id,
                'inventory_item_id' => $inventoryItem->id,
                'quantity_adjusted' => $quantity, // Use quantity_adjusted field
                'unit_cost' => $baseUnitCost, // Set initial cost
                'reason' => 'Initial stock',
            ]);

            // Approve to trigger observer which will create movement
            $adjustment->update(['status' => AdjustmentStatus::Approved]);
        }
    }

    private function createPurchaseBills(array $offerings, array $vendors): void
    {
        $now = now();

        // Start 60 days ago and increment forward to ensure chronological order
        $startDate = $now->copy()->subDays(60);

        // Create 5 purchase bills (60 days ago to 30 days ago, evenly spaced)
        foreach (range(1, 2) as $billIndex) {
            $vendor = $vendors[array_rand($vendors)];

            // Calculate date: each bill is ~6 days apart (30 days / 5 bills)
            $dayOffset = ($billIndex - 1) * 6; // 0, 6, 12, 18, 24 days from start
            $billDate = $startDate->copy()->addDays($dayOffset);

            // Create bill with realistic status (goods received, may or may not be paid yet)
            $bill = Bill::create([
                'company_id' => $this->company->id,
                'vendor_id' => $vendor->id,
                'bill_number' => 'BILL-' . $billDate->format('Ymd') . '-' . str_pad($billIndex, 4, '0', STR_PAD_LEFT),
                'date' => $billDate,
                'due_date' => $billDate->copy()->addDays(30),
                'status' => $this->faker->randomElement([
                    BillStatus::Open,    // Received but not paid
                    BillStatus::Partial, // Partially paid
                    BillStatus::Paid,    // Fully paid
                ]),
                'currency_code' => 'MYR',
                'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;

            // Add 2-4 random items to each bill
            $itemCount = $this->faker->numberBetween(2, 4);
            $selectedOfferings = $this->faker->randomElements($offerings, $itemCount);

            foreach ($selectedOfferings as $offeringData) {
                $offering = $offeringData['offering'];
                $inventoryItem = $offeringData['inventoryItem'];
                $baseUnitCost = $offeringData['unit_cost'];

                // Vary cost over time: ±20% variation based on bill index (simulate price changes)
                $costVariation = ($billIndex - 3) * 0.1; // -0.2, -0.1, 0, 0.1, 0.2
                $unitCost = (int) ($baseUnitCost * (1 + $costVariation));

                $quantity = $this->faker->numberBetween(10, 50);
                $unitPrice = $unitCost; // Use the varied unit cost

                DocumentLineItem::create([
                    'documentable_type' => Bill::class,
                    'documentable_id' => $bill->id,
                    'company_id' => $this->company->id,
                    'offering_id' => $offering->id,
                    'description' => $offering->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ]);

                $subtotal += $quantity * $unitPrice;
            }

            // Update bill totals
            $bill->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            // Reload bill completely with line items to ensure they're available
            $bill = $bill->fresh(['lineItems.offering', 'vendor']);

            echo "  Bill #{$bill->id}: {$bill->bill_number} - {$bill->lineItems->count()} line items\n";

            // Delete any existing transaction and recreate it with all line items
            if ($bill->initialTransaction) {
                $bill->initialTransaction->delete();
            }

            $bill->createInitialTransaction();
            $bill = $bill->fresh();
            echo "    Created transaction #{$bill->initialTransaction->id} with {$bill->initialTransaction->journalEntries->count()} journal entries\n";

            // Record payment for Paid/Partial bills
            if ($bill->status === BillStatus::Paid || $bill->status === BillStatus::Partial) {
                $bankAccount = \App\Models\Banking\BankAccount::first();

                $paymentAmount = $bill->status === BillStatus::Paid
                    ? $bill->total // Full payment
                    : (int) ($bill->total * $this->faker->randomFloat(2, 0.3, 0.7)); // Partial: 30-70%

                // Ensure payment date is between bill date and today (not in future)
                $maxDaysUntilToday = max(1, $now->diffInDays($billDate));
                $daysToAdd = $this->faker->numberBetween(5, min(25, $maxDaysUntilToday));
                $paymentDate = $billDate->copy()->addDays($daysToAdd);

                $bill->recordPayment([
                    'amount' => $paymentAmount,
                    'posted_at' => $paymentDate,
                    'payment_method' => $this->faker->randomElement(['cash', 'bank_payment', 'check']),
                    'bank_account_id' => $bankAccount->id,
                    'notes' => 'Payment recorded by seeder',
                ]);
            }
        }
    }

    private function createSalesInvoices(array $offerings, array $clients): void
    {
        $now = now();

        // Start 29 days ago and increment forward to ensure chronological order
        $startDate = $now->copy()->subDays(29);

        // Create 5 sales invoices (29 days ago to 1 day ago, evenly spaced)
        foreach (range(1, 3) as $invoiceIndex) {
            $client = $clients[array_rand($clients)];

            // Calculate date: each invoice is ~7 days apart (28 days / 4 intervals)
            $dayOffset = ($invoiceIndex - 1) * 7; // 0, 7, 14, 21, 28 days from start
            $invoiceDate = $startDate->copy()->addDays($dayOffset);

            // Create invoice as Draft first
            $invoice = Invoice::create([
                'company_id' => $this->company->id,
                'client_id' => $client->id,
                'invoice_number' => 'INV-' . $invoiceDate->format('Ymd') . '-' . str_pad($invoiceIndex, 4, '0', STR_PAD_LEFT),
                'date' => $invoiceDate,
                'due_date' => $invoiceDate->copy()->addDays(30),
                'status' => InvoiceStatus::Draft, // Start as Draft
                'currency_code' => 'MYR',
                'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
                'subtotal' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;

            // Add 1-3 random items to each invoice
            $itemCount = $this->faker->numberBetween(1, 3);
            $selectedOfferings = $this->faker->randomElements($offerings, $itemCount);

            foreach ($selectedOfferings as $offeringData) {
                $offering = $offeringData['offering'];
                $quantity = $this->faker->numberBetween(5, 20);
                $unitPrice = $offering->price ?? $this->faker->numberBetween(1000, 10000);

                DocumentLineItem::create([
                    'documentable_type' => Invoice::class,
                    'documentable_id' => $invoice->id,
                    'company_id' => $this->company->id,
                    'offering_id' => $offering->id,
                    'description' => $offering->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ]);

                $subtotal += $quantity * $unitPrice;
            }

            // Update invoice totals
            $invoice->update([
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);

            // Change status from Draft to Sent/Unpaid to trigger observer
            // This simulates the real flow: goods ship when invoice is approved/sent
            $finalStatus = $this->faker->randomElement([
                InvoiceStatus::Sent,    // Invoice sent, awaiting payment
                InvoiceStatus::Partial, // Partially paid
                InvoiceStatus::Paid,    // Fully paid
            ]);
            $invoice->update(['status' => $finalStatus]);

            // Record payment for Paid/Partial invoices
            if ($finalStatus === InvoiceStatus::Paid || $finalStatus === InvoiceStatus::Partial) {
                $bankAccount = \App\Models\Banking\BankAccount::first();

                $paymentAmount = $finalStatus === InvoiceStatus::Paid
                    ? $invoice->total // Full payment
                    : (int) ($invoice->total * $this->faker->randomFloat(2, 0.3, 0.7)); // Partial: 30-70%

                // Ensure payment date is between invoice date and today (not in future)
                $maxDaysUntilToday = max(1, $now->diffInDays($invoiceDate));
                $daysToAdd = $this->faker->numberBetween(5, min(25, $maxDaysUntilToday));
                $paymentDate = $invoiceDate->copy()->addDays($daysToAdd);

                $invoice->recordPayment([
                    'amount' => $paymentAmount,
                    'posted_at' => $paymentDate,
                    'payment_method' => $this->faker->randomElement(['cash', 'bank_payment', 'check']),
                    'bank_account_id' => $bankAccount->id,
                    'notes' => 'Payment recorded by seeder',
                ]);
            }
        }
    }

    private function createDamageAdjustments(array $offerings, Warehouse $warehouse): void
    {
        $now = now();

        // Start 14 days ago and increment forward for chronological order
        $startDate = $now->copy()->subDays(14);

        // Create 3 damage adjustments (14, 9, 4 days ago - evenly spaced)
        foreach (range(1, 3) as $adjustmentIndex) {
            $offeringData = $offerings[array_rand($offerings)];
            $inventoryItem = $offeringData['inventoryItem'];

            // Calculate date: each adjustment is ~5 days apart
            $dayOffset = ($adjustmentIndex - 1) * 5; // 0, 5, 10 days from start
            $adjustmentDate = $startDate->copy()->addDays($dayOffset);
            $quantity = $this->faker->numberBetween(-10, -2); // Negative for damage

            // Create adjustment as Draft first
            $adjustment = InventoryAdjustment::create([
                'company_id' => $this->company->id,
                'warehouse_id' => $warehouse->id,
                'adjustment_number' => 'DMG-' . $inventoryItem->sku . '-' . $adjustmentDate->format('Ymd'),
                'adjustment_date' => $adjustmentDate,
                'adjustment_type' => \App\Enums\Inventory\AdjustmentType::Damage,
                'status' => AdjustmentStatus::Draft,
                'reason' => 'Damaged goods',
            ]);

            InventoryAdjustmentItem::create([
                'company_id' => $this->company->id, // Explicitly set company_id
                'adjustment_id' => $adjustment->id,
                'inventory_item_id' => $inventoryItem->id,
                'quantity_adjusted' => $quantity, // Use quantity_adjusted field (negative for damage)
                'reason' => 'Damaged goods',
            ]);

            // Approve to trigger observer which will create movement
            $adjustment->update(['status' => AdjustmentStatus::Approved]);
        }
    }
}
