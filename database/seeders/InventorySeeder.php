<?php

namespace Database\Seeders;

use App\Enums\Inventory\TrackMethod;
use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStockLevel;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            $this->command->error('No company found. Please create a company first.');

            return;
        }

        $this->command->info('Creating warehouses...');

        // Create warehouses
        $warehouses = [
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
            ],
            [
                'name' => 'Secondary Warehouse',
                'code' => 'SEC',
                'address' => '456 Distribution Ave',
                'city' => 'Kepala Batas',
                'state' => 'Pulau Pinang',
                'postal_code' => '13200',
                'country' => 'MYS',
                'contact_name' => 'Jane Supervisor',
                'contact_phone' => '+1-555-0200',
                'contact_email' => 'secondary@warehouse.test',
                'active' => false,
            ],
        ];

        $createdWarehouses = collect();
        foreach ($warehouses as $warehouseData) {
            $warehouse = Warehouse::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $warehouseData['code'],
                ],
                array_merge($warehouseData, [
                    'company_id' => $company->id,
                    'created_by' => 1,
                ])
            );
            $createdWarehouses->push($warehouse);
            $this->command->info("  Created/Found warehouse: {$warehouse->name}");
        }

        $this->command->info('Creating inventory items...');

        // Get or create product offerings
        $products = [
            [
                'name' => 'Laptop Computer',
                'type' => 'product',
                'sku' => 'COMP-LAP-001',
                'track_method' => TrackMethod::FIFO,
                'description' => 'High-performance laptop computer',
            ],
            [
                'name' => 'Wireless Mouse',
                'type' => 'product',
                'sku' => 'ACCS-MOU-001',
                'track_method' => TrackMethod::Average,
                'description' => 'Ergonomic wireless mouse',
            ],
            [
                'name' => 'Monitor 27"',
                'type' => 'product',
                'sku' => 'DISP-MON-027',
                'track_method' => TrackMethod::LIFO,
                'description' => '27-inch 4K monitor',
            ],
            [
                'name' => 'Keyboard Mechanical',
                'type' => 'product',
                'sku' => 'ACCS-KEY-001',
                'track_method' => TrackMethod::FIFO,
                'description' => 'Mechanical keyboard with RGB',
            ],
        ];

        $inventoryService = app(InventoryService::class);

        foreach ($products as $productData) {
            // Create or find offering
            $offering = Offering::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $productData['name'],
                ],
                [
                    'type' => $productData['type'],
                    'description' => $productData['description'],
                    'price' => random_int(50000, 200000), // $500 to $2000
                    'sellable' => true,
                    'purchasable' => true,
                    'stockable' => true,
                ]
            );

            // Update existing offerings to ensure they have the correct flags
            if (! $offering->wasRecentlyCreated) {
                $offering->update([
                    'sellable' => true,
                    'purchasable' => true,
                    'stockable' => true,
                ]);
            }

            // Create or find inventory item
            $inventoryItem = InventoryItem::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'offering_id' => $offering->id,
                ],
                [
                    'sku' => $productData['sku'],
                    'track_method' => $productData['track_method'],
                    'reorder_level' => random_int(5, 20),
                    'reorder_quantity' => random_int(20, 50),
                    'track_batches' => true,
                    'active' => true,
                    'created_by' => 1,
                ]
            );

            // Update existing inventory items to ensure they are active
            if (! $inventoryItem->wasRecentlyCreated && ! $inventoryItem->active) {
                $inventoryItem->update(['active' => true]);
            }

            $wasRecentlyCreated = $inventoryItem->wasRecentlyCreated;

            $this->command->info("  Created/Found inventory item: {$inventoryItem->offering->name}");

            // Determine if we need to create initial stock
            $hasStockLevel = InventoryStockLevel::where('inventory_item_id', $inventoryItem->id)->exists();
            $hasMovements = InventoryMovement::where('inventory_item_id', $inventoryItem->id)->exists();

            // Create initial stock in each warehouse when item was just created OR when no stock/movements exist
            if ($wasRecentlyCreated || (! $hasStockLevel && ! $hasMovements)) {
                foreach ($createdWarehouses as $warehouse) {
                    // $quantity = random_int(10, 100);
                    $quantity = 0; // Set initial quantity to 0
                    $unitCost = random_int(30000, 150000); // $300 to $1500

                    // Create batch with an initial received date older than any simulated purchases
                    $initialDaysAgo = random_int(90, 120); // initial stock 3-4 months ago
                    $receivedDate = now()->subDays($initialDaysAgo);

                    // Create batch
                    $batch = $inventoryService->createBatch(
                        item: $inventoryItem,
                        warehouse: $warehouse,
                        quantity: $quantity,
                        unitCost: $unitCost,
                        receivedDate: $receivedDate,
                        batchNumber: "INIT-{$warehouse->code}-" . $receivedDate->format('ymd'),
                    );

                    // Record initial movement (only if none exists for this item in this warehouse)
                    $existingMovement = InventoryMovement::where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->exists();

                    if (! $existingMovement) {
                        $inventoryService->recordMovement(
                            item: $inventoryItem,
                            warehouse: $warehouse,
                            quantity: $quantity,
                            movementType: \App\Enums\Inventory\MovementType::Initial,
                            unitCost: $unitCost,
                            movementDate: $receivedDate,
                            notes: "Initial stock for {$warehouse->name}"
                        );
                    }

                    $this->command->info("    Added {$quantity} units to {$warehouse->name}");
                }
            } else {
                $this->command->info('    Inventory item already exists, skipping initial stock creation.');
            }

            // Simulate purchase of stock (only for main warehouse and if it's active)
            $mainWarehouse = $createdWarehouses->firstWhere('is_default', true);
            if ($mainWarehouse && $mainWarehouse->active) {
                $this->simulatePurchase($inventoryService, $inventoryItem, $mainWarehouse);
            }

            // Simulate sales transactions
            if ($mainWarehouse && $mainWarehouse->active) {
                $this->simulateSales($inventoryService, $inventoryItem, $mainWarehouse);
            }

            // Simulate damaged stock adjustment
            if ($mainWarehouse && $mainWarehouse->active) {
                $this->simulateDamageAdjustment($inventoryService, $inventoryItem, $mainWarehouse);
            }
        }

        $this->command->info('Inventory seeding completed!');
    }

    /**
     * Simulate purchasing stock for an item
     */
    private function simulatePurchase(
        InventoryService $inventoryService,
        InventoryItem $inventoryItem,
        Warehouse $warehouse
    ): void {
        $this->command->info('    Simulating purchase of stock...');

        // Simulate 2-3 purchases
        $purchaseCount = random_int(2, 3);

        for ($i = 1; $i <= $purchaseCount; $i++) {
            $quantity = random_int(20, 50);
            $unitCost = random_int(30000, 150000); // MYR 300 to MYR 1500
            // Purchases should be more recent than initial stock but older than sales/adjustments
            $daysAgo = random_int(30, 60);
            $batchNumber = "PO-{$warehouse->code}-" . now()->subDays($daysAgo)->format('ymd') . "-{$i}";

            // Create batch for the purchase
            $batch = $inventoryService->createBatch(
                item: $inventoryItem,
                warehouse: $warehouse,
                quantity: $quantity,
                unitCost: $unitCost,
                receivedDate: now()->subDays($daysAgo),
                batchNumber: $batchNumber,
            );

            // Record purchase movement
            $inventoryService->recordMovement(
                item: $inventoryItem,
                warehouse: $warehouse,
                quantity: $quantity,
                movementType: \App\Enums\Inventory\MovementType::Purchase,
                unitCost: $unitCost,
                movementDate: now()->subDays($daysAgo),
                notes: "Purchase Order #{$i} - {$quantity} units received"
            );

            $this->command->info("      Purchase #{$i}: +{$quantity} units @ " . money($unitCost, 'MYR')->format() . " ({$daysAgo} days ago)");
        }
    }

    /**
     * Simulate selling stock for an item
     */
    private function simulateSales(
        InventoryService $inventoryService,
        InventoryItem $inventoryItem,
        Warehouse $warehouse
    ): void {
        $this->command->info('    Simulating sales transactions...');

        // Get current stock level
        $stockLevel = \App\Models\Inventory\InventoryStockLevel::where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if (! $stockLevel || $stockLevel->quantity_available <= 0) {
            $this->command->info('      No stock available for sales simulation');

            return;
        }

        // Simulate 1-3 sales (ensure we don't sell more than available)
        $maxSalesCount = min(3, floor($stockLevel->quantity_available / 5)); // At least 5 units per sale
        if ($maxSalesCount < 1) {
            $this->command->info('      Insufficient stock for sales simulation');

            return;
        }

        $salesCount = random_int(1, max(1, $maxSalesCount));
        $remainingStock = $stockLevel->quantity_available;

        for ($i = 1; $i <= $salesCount; $i++) {
            // Don't sell more than what's available
            $maxQuantity = floor($remainingStock / ($salesCount - $i + 1));
            if ($maxQuantity < 1) {
                break;
            }

            $quantity = random_int(1, min(10, $maxQuantity));
            // Sales should be recent
            $daysAgo = random_int(1, 29);

            // Calculate COGS for this sale
            $cogsBreakdown = $inventoryService->calculateCOGS($inventoryItem, $warehouse, $quantity);
            $totalCogs = $cogsBreakdown['total_cost'];
            $avgUnitCost = $quantity > 0 ? intval($totalCogs / $quantity) : 0;

            // Record sale movement (negative quantity)
            $inventoryService->recordMovement(
                item: $inventoryItem,
                warehouse: $warehouse,
                quantity: -$quantity, // Negative for outbound
                movementType: \App\Enums\Inventory\MovementType::Sale,
                unitCost: $avgUnitCost,
                movementDate: now()->subDays($daysAgo),
                notes: "Sales Order #{$i} - {$quantity} units sold"
            );

            $remainingStock -= $quantity;

            $this->command->info("      Sale #{$i}: -{$quantity} units (COGS: " . money($totalCogs, 'MYR')->format() . ") ({$daysAgo} days ago)");
        }
    }

    /**
     * Simulate damaged stock adjustment
     */
    private function simulateDamageAdjustment(
        InventoryService $inventoryService,
        InventoryItem $inventoryItem,
        Warehouse $warehouse
    ): void {
        $this->command->info('    Simulating damage adjustment...');

        // Get current stock level
        $stockLevel = \App\Models\Inventory\InventoryStockLevel::where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if (! $stockLevel || $stockLevel->quantity_available <= 0) {
            $this->command->info('      No stock available for adjustment');

            return;
        }

        // Damage 1-5% of current stock (minimum 1 unit)
        $damagePercent = random_int(1, 5);
        $damageQuantity = max(1, floor($stockLevel->quantity_available * ($damagePercent / 100)));

        // Don't damage more than 10 units in this simulation
        $damageQuantity = min($damageQuantity, 10);

        // Adjustments (damages) should be very recent
        $daysAgo = random_int(1, 14);
        $adjustmentDate = now()->subDays($daysAgo);

        // Calculate average cost for the damaged items
        $cogsBreakdown = $inventoryService->calculateCOGS($inventoryItem, $warehouse, $damageQuantity);
        $avgUnitCost = $damageQuantity > 0 ? intval($cogsBreakdown['total_cost'] / $damageQuantity) : 0;

        // Create the InventoryAdjustment record
        $adjustment = \App\Models\Inventory\InventoryAdjustment::create([
            'company_id' => $inventoryItem->company_id,
            'warehouse_id' => $warehouse->id,
            'adjustment_number' => 'ADJ-' . $warehouse->code . '-' . $adjustmentDate->format('ymd') . '-' . random_int(100, 999),
            'adjustment_date' => $adjustmentDate,
            'status' => \App\Enums\Inventory\AdjustmentStatus::Approved,
            'reason' => 'Stock damaged during handling',
            'approved_by' => 1,
            'approved_at' => $adjustmentDate->addMinutes(30),
            'created_by' => 1,
        ]);

        // Create the adjustment item
        $adjustmentItem = \App\Models\Inventory\InventoryAdjustmentItem::create([
            'company_id' => $inventoryItem->company_id,
            'adjustment_id' => $adjustment->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_before' => $stockLevel->quantity_available,
            'quantity_after' => $stockLevel->quantity_available - $damageQuantity,
            'quantity_adjusted' => -$damageQuantity, // Negative for reduction
            'unit_cost' => $avgUnitCost,
            'reason' => "{$damageQuantity} units damaged - written off",
        ]);

        // Record movement to update stock levels
        $inventoryService->recordMovement(
            item: $inventoryItem,
            warehouse: $warehouse,
            quantity: -$damageQuantity, // Negative for reduction
            movementType: \App\Enums\Inventory\MovementType::Adjustment,
            unitCost: $avgUnitCost,
            movementDate: $adjustmentDate,
            notes: "Adjustment #{$adjustment->adjustment_number} - Stock damaged during handling"
        );

        $this->command->info("      Adjustment #{$adjustment->adjustment_number}: -{$damageQuantity} units damaged (Loss: " . money($cogsBreakdown['total_cost'], 'MYR')->format() . ") ({$daysAgo} days ago)");
    }
}
