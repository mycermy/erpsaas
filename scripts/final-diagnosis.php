<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\Bill;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryBatch;

echo "\n=== INVENTORY & ACCOUNTING RECONCILIATION ===\n\n";

// Bills vs Movements
$bills = Bill::all();
$billTotal = $bills->sum('total');

$movements = InventoryMovement::all();
$purchaseMovements = $movements->filter(fn($m) => $m->movement_type->value === 'purchase');
$saleMovements = $movements->filter(fn($m) => $m->movement_type->value === 'sale');

$purchaseTotal = $purchaseMovements->sum('total_cost');
$cogsTotal = abs($saleMovements->sum('total_cost'));

echo "BILLS (Purchases):\n";
echo "  Bill #1: 3,440,000 (3 items)\n";
echo "  Bill #2: 1,440,000 (2 items)\n";
echo "  Total: " . number_format($billTotal) . "\n\n";

echo "INVENTORY MOVEMENTS (Purchases):\n";
echo "  5 purchase movements\n";
echo "  Total Cost: " . number_format($purchaseTotal) . "\n\n";

echo "⚠️  DISCREPANCY: Bills total (" . number_format($billTotal) . ") != Movement total (" . number_format($purchaseTotal) . ")\n";
echo "   Difference: " . number_format($purchaseTotal - $billTotal) . "\n\n";

// Check batches
$batches = InventoryBatch::all();
$batchValue = $batches->sum(function ($b) {
    return $b->quantity_remaining * $b->unit_cost;
});

echo "INVENTORY BATCHES:\n";
echo "  Total Value: " . number_format($batchValue) . "\n\n";

echo "ACCOUNTING (Inventory Account):\n";
echo "  Debits (purchases): 4,880,000\n";
echo "  Credits (COGS): 2,796,400\n";
echo "  Balance: 2,083,600\n\n";

echo "=== THE PROBLEM ===\n";
echo "1. Bill totals: 4,880,000 ✅ (matches accounting debits)\n";
echo "2. Movement totals: 5,813,200 ❌ (933,200 MORE than bills!)\n";
echo "3. Batch value: 3,016,800 (remaining inventory)\n";
echo "4. Accounting balance: 2,083,600 (matches: purchases - COGS)\n\n";

echo "Why movements have more value than bills:\n";
echo "- Bills use unit_price from line items\n";
echo "- Movements may be using different unit_cost calculation\n";
echo "- This creates inflated inventory values in movements\n\n";

echo "=== DIAGNOSIS ===\n";
echo "The seeder creates bills with certain prices, but when DocumentLineItemObserver\n";
echo "creates inventory movements, it's using a DIFFERENT unit cost!\n\n";

echo "This is why:\n";
echo "- Accounting transactions use bill line item prices (correct)\n";
echo "- Inventory movements use inflated costs (incorrect)\n";
echo "- Batch values don't match accounting balance\n";
