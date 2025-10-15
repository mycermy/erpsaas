<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\Accounting\BillStatus;
use App\Models\Accounting\Bill;
use App\Models\Company;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;

DB::beginTransaction();

try {
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  BATCH DUPLICATION TEST                                        ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";

    // Setup
    $company = Company::first();
    $warehouse = Warehouse::where('company_id', $company->id)->where('is_default', true)->first();
    $offering = \App\Models\Common\Offering::where('company_id', $company->id)
        ->whereHas('inventoryItem')
        ->first();
    $inventoryItem = $offering->inventoryItem;
    $vendor = \App\Models\Common\Vendor::factory()->create(['company_id' => $company->id]);

    echo "📦 Item: {$offering->name}\n";
    echo "🏢 Warehouse: {$warehouse->name}\n\n";

    // Count batches before
    $batchesBefore = InventoryBatch::where('inventory_item_id', $inventoryItem->id)
        ->where('warehouse_id', $warehouse->id)
        ->count();

    echo "Batches before: {$batchesBefore}\n\n";

    // Create a bill with ONE line item
    echo "Creating bill with ONE line item (100 units)...\n";

    $bill = Bill::create([
        'company_id' => $company->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'TEST-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => 100 * 90000,
        'total' => 100 * 90000,
    ]);

    echo "Bill created: #{$bill->bill_number}\n";

    // Create line item
    $lineItem = $bill->lineItems()->create([
        'company_id' => $company->id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => 100,
        'unit_price' => 90000,
    ]);

    echo "Line item created: {$lineItem->id}\n\n";

    // Count batches after
    $batchesAfter = InventoryBatch::where('inventory_item_id', $inventoryItem->id)
        ->where('warehouse_id', $warehouse->id)
        ->count();

    $newBatches = $batchesAfter - $batchesBefore;

    echo "Batches after: {$batchesAfter}\n";
    echo "New batches created: {$newBatches}\n\n";

    if ($newBatches === 1) {
        echo "✅ PASS: Only 1 batch created (no duplication)\n";
    } else {
        echo "❌ FAIL: {$newBatches} batches created (expected 1)\n\n";

        // Show the new batches
        $batches = InventoryBatch::where('inventory_item_id', $inventoryItem->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderBy('id', 'desc')
            ->limit($newBatches)
            ->get();

        echo "New batches:\n";
        foreach ($batches as $batch) {
            echo "  - Batch #{$batch->id}: {$batch->batch_number}, Qty: {$batch->quantity_remaining}\n";
        }
    }

    DB::rollBack();
    echo "\n✓ Transaction rolled back\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo '❌ Error: ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

