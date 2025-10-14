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
                ]
            );

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

            $wasRecentlyCreated = $inventoryItem->wasRecentlyCreated;

            $this->command->info("  Created/Found inventory item: {$inventoryItem->offering->name}");

            // Determine if we need to create initial stock
            $hasStockLevel = InventoryStockLevel::where('inventory_item_id', $inventoryItem->id)->exists();
            $hasMovements = InventoryMovement::where('inventory_item_id', $inventoryItem->id)->exists();

            // Create initial stock in each warehouse when item was just created OR when no stock/movements exist
            if ($wasRecentlyCreated || (! $hasStockLevel && ! $hasMovements)) {
                foreach ($createdWarehouses as $warehouse) {
                    $quantity = random_int(10, 100);
                    $unitCost = random_int(30000, 150000); // $300 to $1500

                    // Create batch
                    $batch = $inventoryService->createBatch(
                        item: $inventoryItem,
                        warehouse: $warehouse,
                        quantity: $quantity,
                        unitCost: $unitCost,
                        receivedDate: now()->subDays(random_int(1, 30)),
                        batchNumber: "INIT-{$warehouse->code}-" . now()->format('ymd'),
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
                            movementDate: now()->subDays(random_int(1, 30)),
                            notes: "Initial stock for {$warehouse->name}"
                        );
                    }

                    $this->command->info("    Added {$quantity} units to {$warehouse->name}");
                }
            } else {
                $this->command->info('    Inventory item already exists, skipping initial stock creation.');
            }
        }

        $this->command->info('Inventory seeding completed!');
    }
}
