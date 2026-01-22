<?php

use App\Models\Accounting\Bill;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$bill = Bill::with(['lineItems.offering.inventoryItem'])->find(1);

echo "Bill #{$bill->id}: {$bill->bill_number}\n";
echo "Line Items Count: " . $bill->lineItems->count() . "\n";
echo "Vendor loaded: " . ($bill->vendor ? 'YES' : 'NO') . "\n\n";

foreach ($bill->lineItems as $index => $lineItem) {
    echo "Line Item #{$lineItem->id} (Index {$index}):\n";
    echo "  Offering: {$lineItem->offering->name}\n";
    echo "  Quantity: {$lineItem->quantity}\n";
    echo "  Unit Price: {$lineItem->unit_price}\n";
    echo "  Subtotal: {$lineItem->subtotal}\n";
    echo "  Raw Subtotal: {$lineItem->getRawOriginal('subtotal')}\n";
    echo "  Has Inventory Item: " . ($lineItem->offering->inventoryItem ? 'YES' : 'NO') . "\n";
    if ($lineItem->offering->inventoryItem) {
        echo "  Debit Account: Inventory (Asset)\n";
    } elseif ($lineItem->offering->expense_account_id) {
        echo "  Debit Account: Expense Account #{$lineItem->offering->expense_account_id}\n";
    } else {
        echo "  ❌ NO DEBIT ACCOUNT!\n";
    }
    echo "\n";
}

echo "Deleting existing transaction...\n";
if ($bill->initialTransaction) {
    $bill->initialTransaction->delete();
}

echo "Creating new transaction...\n";
$bill->load('vendor'); // Make sure vendor is loaded
try {
    $bill->createInitialTransaction();
    echo "Transaction created successfully!\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Done! Checking journal entries...\n";
$transaction = $bill->fresh()->initialTransaction;
echo "Transaction #{$transaction->id} (fresh load)\n";
$journalEntries = \App\Models\Accounting\JournalEntry::where('transaction_id', $transaction->id)->get();
echo "Journal entries from direct query: " . $journalEntries->count() . "\n";
echo "Journal entries from relationship: " . $transaction->journalEntries->count() . "\n";
foreach ($journalEntries as $entry) {
    $debit = $entry->type->value === 'debit' ? $entry->amount : 0;
    $credit = $entry->type->value === 'credit' ? $entry->amount : 0;
    echo "  {$entry->account->name}: Debit=" . number_format($debit / 100, 2) . " Credit=" . number_format($credit / 100, 2) . "\n";
}
