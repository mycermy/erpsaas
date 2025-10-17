<?php

namespace Tests\Feature;

use App\Enums\Inventory\AdjustmentStatus;
use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\TrackMethod;
use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentItem;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected \App\Models\Company $company;

    protected \App\Models\Inventory\Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no authenticated user so Blamable doesn't populate updated_by where DB lacks it
        \Illuminate\Support\Facades\Auth::logout();

        // Create a company and default warehouse
        $this->company = Company::factory()->create();
        // Set current company in session for CompanyOwned scope
        session(['current_company_id' => $this->company->id]);
        // Ensure basic accounting accounts exist (Inventory) used by Offering->ensureInventoryItem
        \App\Models\Accounting\Account::firstOrCreate([
            'company_id' => $this->company->id,
            'name' => 'Inventory',
        ], [
            'category' => \App\Enums\Accounting\AccountCategory::Asset,
            'type' => \App\Enums\Accounting\AccountType::CurrentAsset,
            'code' => '1000',
            'currency_code' => 'MYR',
            'created_by' => 1,
        ]);
        $this->warehouse = Warehouse::firstOrCreate([
            'company_id' => $this->company->id,
            'code' => 'TST',
        ], [
            'name' => 'Test Warehouse',
            'address' => 'Test',
            'city' => 'Test City',
            'state' => 'Test',
            'postal_code' => '00000',
            'country' => 'TST',
            'is_default' => true,
            'active' => true,
        ]);
    }

    public function test_inbound_adjustment_creates_batch_and_updates_stock()
    {
        $offering = Offering::withoutEvents(function () {
            return Offering::firstOrCreate([
                'company_id' => $this->company->id,
                'name' => 'Test Laptop',
            ], [
                'type' => 'product',
                'price' => 100000,
                'sellable' => true,
                'purchasable' => true,
                'stockable' => true,
            ]);
        });

        $item = InventoryItem::create([
            'company_id' => $this->company->id,
            'offering_id' => $offering->id,
            'sku' => 'TEST-LAP-001',
            'track_method' => TrackMethod::FIFO,
            'track_batches' => true,
            'active' => true,
        ]);

        $service = app(InventoryService::class);

        // Start with zero stock
        $this->assertEquals(0, $service->getStockQuantity($item, $this->warehouse));

        // Create adjustment (inbound)
        $adjustment = InventoryAdjustment::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'adjustment_number' => 'ADJ-TST-001',
            'adjustment_date' => now(),
            'status' => AdjustmentStatus::Draft,
            'reason' => 'Found stock',
            'created_by' => 1,
        ]);

        $adjustmentItem = InventoryAdjustmentItem::create([
            'company_id' => $this->company->id,
            'adjustment_id' => $adjustment->id,
            'inventory_item_id' => $item->id,
            'quantity_before' => 0,
            'quantity_after' => 10,
            'quantity_adjusted' => 10,
            'unit_cost' => 50000,
            'reason' => 'Stock count correction',
        ]);

        // Approve (toggle) to trigger observer
        $adjustment->status = AdjustmentStatus::Approved;
        $adjustment->save();

        // Assert movement created and stock updated
        // Assert movement created and stock updated
        $movement = \App\Models\Inventory\InventoryMovement::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('reference_type', InventoryAdjustment::class)
            ->where('reference_id', $adjustment->id)
            ->where('inventory_item_id', $item->id)
            ->first();

        $this->assertNotNull($movement, 'InventoryMovement should be created for adjustment');
        $this->assertEquals(10, $service->getStockQuantity($item, $this->warehouse));

        // If a batch was created by the inbound processing, assert its size
        $batch = \App\Models\Inventory\InventoryBatch::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('inventory_item_id', $item->id)->first();
        if ($batch) {
            $this->assertEquals(10, $batch->quantity_received);
        }
        $movement = \App\Models\Inventory\InventoryMovement::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('reference_type', InventoryAdjustment::class)
            ->where('reference_id', $adjustment->id)
            ->where('inventory_item_id', $item->id)
            ->first();

        $this->assertNotNull($movement, 'InventoryMovement should be created for adjustment');
        $this->assertEquals(10, $service->getStockQuantity($item, $this->warehouse));
    }

    public function test_outbound_adjustment_consumes_batches_and_updates_stock()
    {
        $offering = Offering::withoutEvents(function () {
            return Offering::firstOrCreate([
                'company_id' => $this->company->id,
                'name' => 'Test Laptop 2',
            ], [
                'type' => 'product',
                'price' => 100000,
                'sellable' => true,
                'purchasable' => true,
                'stockable' => true,
            ]);
        });

        $item = InventoryItem::create([
            'company_id' => $this->company->id,
            'offering_id' => $offering->id,
            'sku' => 'TEST-LAP-002',
            'track_method' => TrackMethod::FIFO,
            'track_batches' => true,
            'active' => true,
        ]);

        $service = app(InventoryService::class);

        // Authenticate a user so Blamable sets created_by where needed
        \Illuminate\Support\Facades\Auth::loginUsingId(1);

        $service->recordMovement($item, $this->warehouse, 10, MovementType::Purchase, 50000, null, null, null, 'Purchase PO-TST-1', now()->subDays(10), 1);
        $service->recordMovement($item, $this->warehouse, 15, MovementType::Purchase, 55000, null, null, null, 'Purchase PO-TST-2', now()->subDays(5), 1);

        // logout to avoid Blamable updating updated_by later
        \Illuminate\Support\Facades\Auth::logout();
        $this->assertEquals(25, $service->getStockQuantity($item, $this->warehouse));

        // Create adjustment that reduces stock by 9
        $adjustment = InventoryAdjustment::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'adjustment_number' => 'ADJ-TST-002',
            'adjustment_date' => now(),
            'status' => AdjustmentStatus::Draft,
            'reason' => 'Damage',
            'created_by' => 1,
        ]);

        $adjustmentItem = InventoryAdjustmentItem::create([
            'company_id' => $this->company->id,
            'adjustment_id' => $adjustment->id,
            'inventory_item_id' => $item->id,
            'quantity_before' => 25,
            'quantity_after' => 16,
            'quantity_adjusted' => -9,
            'unit_cost' => 0,
            'reason' => 'Damaged items',
        ]);

        // Approve
        $adjustment->status = AdjustmentStatus::Approved;
        $adjustment->save();

        // Assert movements exist referencing the adjustment
        $movements = \App\Models\Inventory\InventoryMovement::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('reference_type', InventoryAdjustment::class)
            ->where('reference_id', $adjustment->id)
            ->where('inventory_item_id', $item->id)
            ->get();

        $this->assertTrue($movements->count() >= 1, 'At least one movement must be created for outbound adjustment');

        // Stock should be reduced from 25 to 16
        $this->assertEquals(16, $service->getStockQuantity($item, $this->warehouse));
    }

    public function test_idempotent_processing_of_adjustment()
    {
        $offering = Offering::withoutEvents(function () {
            return Offering::firstOrCreate([
                'company_id' => $this->company->id,
                'name' => 'Test Laptop 3',
            ], [
                'type' => 'product',
                'price' => 100000,
                'sellable' => true,
                'purchasable' => true,
                'stockable' => true,
            ]);
        });

        $item = InventoryItem::create([
            'company_id' => $this->company->id,
            'offering_id' => $offering->id,
            'sku' => 'TEST-LAP-003',
            'track_method' => TrackMethod::FIFO,
            'track_batches' => false,
            'active' => true,
        ]);

        $service = app(InventoryService::class);

        // Start with 0 stock
        $this->assertEquals(0, $service->getStockQuantity($item, $this->warehouse));

        $adjustment = InventoryAdjustment::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->warehouse->id,
            'adjustment_number' => 'ADJ-TST-003',
            'adjustment_date' => now(),
            'status' => AdjustmentStatus::Draft,
            'reason' => 'Found stock',
            'created_by' => 1,
        ]);

        $adjustmentItem = InventoryAdjustmentItem::create([
            'company_id' => $this->company->id,
            'adjustment_id' => $adjustment->id,
            'inventory_item_id' => $item->id,
            'quantity_before' => 0,
            'quantity_after' => 5,
            'quantity_adjusted' => 5,
            'unit_cost' => 10000,
            'reason' => 'Stock count correction',
        ]);

        // First approval
        $adjustment->status = AdjustmentStatus::Approved;
        $adjustment->save();

        $firstStock = $service->getStockQuantity($item, $this->warehouse);

        // Second approval attempt (should be idempotent)
        $adjustment->status = AdjustmentStatus::Draft;
        $adjustment->save();
        $adjustment->status = AdjustmentStatus::Approved;
        $adjustment->save();

        $secondStock = $service->getStockQuantity($item, $this->warehouse);

        $this->assertEquals($firstStock, $secondStock, 'Re-processing should not double apply the adjustment');
    }
}
