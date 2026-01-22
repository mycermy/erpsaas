<?php

use App\Models\Accounting\Bill;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$bill = Bill::find(1);

echo "Bill #1 from database\n";
echo "Line Items Count (query): " . $bill->lineItems()->count() . "\n";
echo "Line Items Count (collection): " . $bill->lineItems->count() . "\n\n";

echo "Fresh with explicit load:\n";
$bill = $bill->fresh(['lineItems.offering', 'vendor']);
echo "Line Items Count (query): " . $bill->lineItems()->count() . "\n";
echo "Line Items Count (collection): " . $bill->lineItems->count() . "\n";

echo "\nLine items:\n";
foreach ($bill->lineItems as $index => $lineItem) {
    echo "  #{$index}: {$lineItem->offering->name} - Qty: {$lineItem->quantity}, Subtotal: {$lineItem->subtotal}\n";
}
