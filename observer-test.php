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

echo "\n🧪 Testing Observer WITHOUT Transaction\n\n";

try {
    $offering = Offering::where('name', 'Laptop Computer')->first();
    $vendor = Vendor::first();

    // Create bill WITHOUT transaction
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

    // Add line item - observer SHOULD fire
    $lineItem = $bill->lineItems()->create([
        'company_id' => $offering->company_id,
        'offering_id' => $offering->id,
        'description' => $offering->name,
        'quantity' => 100,
        'unit_price' => 90000,
    ]);

    echo "Line item created: ID #{$lineItem->id}\n";
    echo "Check storage/logs/laravel.log for observer logs\n\n";

    // Clean up
    echo "Cleaning up (deleting test bill)...\n";
    $bill->delete();
    echo "✓ Done\n\n";

} catch (\Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo "{$e->getFile()}:{$e->getLine()}\n";
}
