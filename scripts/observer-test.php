<?php

/**
 * Test WITHOUT Transaction - See if observer fires
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\Accounting\BillStatus;
use App\Models\Accounting\Bill;
use App\Models\Common\Offering;
use App\Models\Common\Vendor;
use Illuminate\Support\Facades\DB;

echo "\n🧪 Testing Observer (wrapped in transaction for safety)\n\n";

DB::beginTransaction();
try {
    $offering = Offering::where('name', 'Laptop Computer')->first();
    $vendor = Vendor::first();

    if (! $offering || ! $vendor) {
        echo "ERROR: Missing fixtures (offering or vendor). Seed test data first.\n";
        DB::rollBack();
        exit(1);
    }

    // Create bill inside transaction
    $bill = Bill::create([
        'company_id' => $offering->company_id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'OBSERVER-TEST-' . now()->timestamp,
        'date' => now(),
        'due_date' => now()->addDays(30),
        'status' => BillStatus::Paid,
        'currency_code' => 'MYR',
        'discount_method' => \App\Enums\Accounting\DocumentDiscountMethod::PerLineItem,
        'subtotal' => 100 * 90000,
        'total' => 100 * 90000,
    ]);

    echo "Bill created: #{$bill->bill_number}\n";
    echo "Adding line item...\n\n";

    $lineItem = $bill->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => 100,
        'unit_price' => 90000,
    ]);

    echo "Line item created: ID #{$lineItem->id}\n";
    echo "Check storage/logs/laravel.log for observer logs\n\n";

    // Roll back so no persistent changes remain
    DB::rollBack();
    echo "✓ Rolled back test transaction\n\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR: {$e->getMessage()}\n";
    echo "{$e->getFile()}:{$e->getLine()}\n";
}
