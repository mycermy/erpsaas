<?php

use App\Models\User;
use Erpsaas\Core\Models\Common\Offering;
use Erpsaas\Core\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\PluginTestCase;
use Zrm\Inventory\Enums\AdjustmentStatus;
use Zrm\Inventory\Models\InventoryAdjustmentImport;
use Zrm\Inventory\Models\InventoryItem;
use Zrm\Inventory\Models\InventoryStockLevel;
use Zrm\Inventory\Models\Warehouse;
use Zrm\Inventory\Services\InventoryAdjustmentImportService;

uses(PluginTestCase::class);

/**
 * @return array{user: User, company: Company, warehouse: Warehouse, inventoryItem: InventoryItem}
 */
function prepareImportContext(): array
{
    Storage::fake('local');

    /** @var User $user */
    $user = User::factory()->withPersonalCompany()->create();

    /** @var Company $company */
    $company = $user->ownedCompanies()->firstOrFail();

    $user->switchCompany($company);
    Auth::login($user);

    $warehouse = Warehouse::create([
        'company_id' => $company->id,
        'name' => 'Main Warehouse',
        'code' => 'MAIN',
        'active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $offering = Offering::create([
        'company_id' => $company->id,
        'name' => 'Laptop Computer',
        'description' => 'Stock tracked laptop',
        'type' => 'product',
        'price' => 100000,
        'sellable' => true,
        'purchasable' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    $offering->stockable = true;
    $offering->save();

    $inventoryItem = InventoryItem::create([
        'company_id' => $company->id,
        'offering_id' => $offering->id,
        'sku' => 'LAPTOP-001',
        'track_method' => 'fifo',
        'reorder_level' => 0,
        'reorder_quantity' => 0,
        'track_batches' => false,
        'active' => true,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $company->id,
        'inventory_item_id' => $inventoryItem->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 10,
        'quantity_reserved' => 0,
        'quantity_available' => 10,
        'average_cost' => 10000,
    ]);

    return compact('user', 'company', 'warehouse', 'inventoryItem');
}

it('provides a strict downloadable csv template example', function () {
    $service = app(InventoryAdjustmentImportService::class);

    $csv = $service->templateExampleAsCsv();
    $lines = array_values(array_filter(explode("\n", trim($csv))));

    expect($lines[0])->toBe('sku,item_name,quantity_counted,reason')
        ->and(count(str_getcsv($lines[0])))->toBe(4)
        ->and($service->expectedHeaders())->toBe([
            'sku',
            'item_name',
            'quantity_counted',
            'reason',
        ]);
});

it('builds a dynamic inventory template sorted by warehouse category and brand with blank input columns', function () {
    $context = prepareImportContext();

    $serviceOffering = Offering::create([
        'company_id' => $context['company']->id,
        'name' => 'Audit Service Item',
        'description' => 'Service typed inventory row for ordering coverage',
        'type' => 'service',
        'price' => 5000,
        'sellable' => true,
        'purchasable' => true,
        'created_by' => $context['user']->id,
        'updated_by' => $context['user']->id,
    ]);

    $serviceOffering->stockable = true;
    $serviceOffering->save();

    $serviceItem = InventoryItem::create([
        'company_id' => $context['company']->id,
        'offering_id' => $serviceOffering->id,
        'sku' => 'SERVICE-001',
        'track_method' => 'fifo',
        'reorder_level' => 0,
        'reorder_quantity' => 0,
        'track_batches' => false,
        'active' => true,
        'created_by' => $context['user']->id,
        'updated_by' => $context['user']->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $context['company']->id,
        'inventory_item_id' => $serviceItem->id,
        'warehouse_id' => $context['warehouse']->id,
        'quantity_on_hand' => 4,
        'quantity_reserved' => 0,
        'quantity_available' => 4,
        'average_cost' => 2000,
    ]);

    $annexWarehouse = Warehouse::create([
        'company_id' => $context['company']->id,
        'name' => 'Annex Warehouse',
        'code' => 'ANNEX',
        'active' => true,
        'created_by' => $context['user']->id,
        'updated_by' => $context['user']->id,
    ]);

    InventoryStockLevel::create([
        'company_id' => $context['company']->id,
        'inventory_item_id' => $context['inventoryItem']->id,
        'warehouse_id' => $annexWarehouse->id,
        'quantity_on_hand' => 7,
        'quantity_reserved' => 0,
        'quantity_available' => 7,
        'average_cost' => 10000,
    ]);

    $rows = app(InventoryAdjustmentImportService::class)
        ->inventoryTemplateRows($context['company'])
        ->values()
        ->all();

    expect(app(InventoryAdjustmentImportService::class)->inventoryTemplateHeaders())->toBe([
        'warehouse_id',
        'warehouse_name',
        'item_category',
        'brand',
        'sku',
        'item_name',
        'current_quantity',
        'quantity_counted',
        'reason',
    ]);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['warehouse_name'])->toBe('Annex Warehouse')
        ->and($rows[0]['sku'])->toBe('LAPTOP-001')
        ->and($rows[0]['quantity_counted'])->toBe('')
        ->and($rows[0]['reason'])->toBe('')
        ->and($rows[1]['warehouse_name'])->toBe('Main Warehouse')
        ->and($rows[1]['item_category'])->toBe('product')
        ->and($rows[1]['sku'])->toBe('LAPTOP-001')
        ->and($rows[2]['warehouse_name'])->toBe('Main Warehouse')
        ->and($rows[2]['item_category'])->toBe('service')
        ->and($rows[2]['sku'])->toBe('SERVICE-001');
});

it('rejects csv files that do not match the strict template header', function () {
    $context = prepareImportContext();

    $path = 'imports/inventory-adjustments/invalid-header.csv';

    Storage::disk('local')->put($path, implode("\n", [
        'sku,item_name,quantity,reason',
        'LAPTOP-001,Laptop Computer,15,EOY stock take',
    ]));

    $service = app(InventoryAdjustmentImportService::class);

    expect(function () use ($service, $path, $context) {
        $service->importFromCsv(
            company: $context['company'],
            warehouse: $context['warehouse'],
            csvPath: $path,
            importedBy: $context['user']->id,
            unknownSkuStrategy: InventoryAdjustmentImportService::UNKNOWN_SKU_STRATEGY_FLAG,
        );
    })->toThrow(ValidationException::class);

    expect(InventoryAdjustmentImport::query()->count())->toBe(1)
        ->and(InventoryAdjustmentImport::query()->first()->status)->toBe('failed');
});

it('imports valid rows and flags unknown skus when strategy is flag', function () {
    $context = prepareImportContext();

    $path = 'imports/inventory-adjustments/flag-unknown.csv';

    Storage::disk('local')->put($path, implode("\n", [
        'sku,item_name,quantity_counted,reason',
        'LAPTOP-001,Laptop Computer,15,EOY stock take',
        'UNKNOWN-001,Unlisted Item,4,Found on shelf',
    ]));

    $service = app(InventoryAdjustmentImportService::class);

    $import = $service->importFromCsv(
        company: $context['company'],
        warehouse: $context['warehouse'],
        csvPath: $path,
        importedBy: $context['user']->id,
        unknownSkuStrategy: InventoryAdjustmentImportService::UNKNOWN_SKU_STRATEGY_FLAG,
    );

    $import->refresh();

    expect($import->status)->toBe('completed_with_errors')
        ->and($import->total_rows)->toBe(2)
        ->and($import->successful_rows)->toBe(1)
        ->and($import->failed_rows)->toBe(1)
        ->and($import->adjustment)->not->toBeNull();

    $adjustment = $import->adjustment->fresh('items');

    expect($adjustment->status)->toBe(AdjustmentStatus::Approved)
        ->and($adjustment->items)->toHaveCount(1)
        ->and($adjustment->items->first()->quantity_before)->toBe(10)
        ->and($adjustment->items->first()->quantity_after)->toBe(15)
        ->and($adjustment->items->first()->quantity_adjusted)->toBe(5);

    expect(InventoryItem::query()->where('sku', 'UNKNOWN-001')->exists())->toBeFalse();
});

it('auto creates unknown sku items when strategy is auto_create', function () {
    $context = prepareImportContext();

    $path = 'imports/inventory-adjustments/auto-create.csv';

    Storage::disk('local')->put($path, implode("\n", [
        'sku,item_name,quantity_counted,reason',
        'NEW-SKU-001,Found Item,3,Found physically during stock take',
    ]));

    $service = app(InventoryAdjustmentImportService::class);

    $import = $service->importFromCsv(
        company: $context['company'],
        warehouse: $context['warehouse'],
        csvPath: $path,
        importedBy: $context['user']->id,
        unknownSkuStrategy: InventoryAdjustmentImportService::UNKNOWN_SKU_STRATEGY_AUTO_CREATE,
    );

    $import->refresh();

    $newItem = InventoryItem::query()->where('company_id', $context['company']->id)->where('sku', 'NEW-SKU-001')->first();

    expect($import->status)->toBe('completed')
        ->and($import->successful_rows)->toBe(1)
        ->and($import->failed_rows)->toBe(0)
        ->and($newItem)->not->toBeNull()
        ->and($newItem->active)->toBeFalse();

    $adjustment = $import->adjustment->fresh('items');

    expect($adjustment->status)->toBe(AdjustmentStatus::Approved)
        ->and($adjustment->items)->toHaveCount(1)
        ->and($adjustment->items->first()->inventory_item_id)->toBe($newItem->id)
        ->and($adjustment->items->first()->quantity_before)->toBe(0)
        ->and($adjustment->items->first()->quantity_after)->toBe(3)
        ->and($adjustment->items->first()->quantity_adjusted)->toBe(3);
});
