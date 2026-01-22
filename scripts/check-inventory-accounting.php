<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\InventoryMovement;

echo "\n=== ACCOUNTING TRANSACTIONS CHECK ===\n\n";

// Check bills
$bills = Bill::all();
echo "=== BILLS ===\n";
echo "Total Bills: " . $bills->count() . "\n";

foreach ($bills as $bill) {
    echo "\nBill: {$bill->bill_number}\n";
    echo "  Line Items: " . $bill->lineItems->count() . "\n";

    // Check if bill has transactions
    $transactions = Transaction::where('transactionable_type', Bill::class)
        ->where('transactionable_id', $bill->id)
        ->get();

    echo "  Transactions: " . $transactions->count() . "\n";

    if ($transactions->isEmpty()) {
        echo "  ⚠️  NO TRANSACTIONS CREATED FOR THIS BILL!\n";
    } else {
        foreach ($transactions as $transaction) {
            $journalEntries = JournalEntry::where('transaction_id', $transaction->id)->get();
            echo "    Transaction #{$transaction->id}: " . $journalEntries->count() . " journal entries\n";

            foreach ($journalEntries as $entry) {
                $account = $entry->account;
                $type = $entry->type->value ?? $entry->type;
                $amount = $entry->amount;
                $category = $account->category->value ?? $account->category;
                echo "      - {$account->name} ({$category}): {$type} {$amount}\n";
            }
        }
    }

    // Check inventory movements for this bill
    $lineItemIds = $bill->lineItems->pluck('id');
    $movements = InventoryMovement::where('reference_type', DocumentLineItem::class)
        ->whereIn('reference_id', $lineItemIds)
        ->get();

    echo "  Inventory Movements: " . $movements->count() . "\n";
}

// Check invoices
$invoices = Invoice::all();
echo "\n=== INVOICES ===\n";
echo "Total Invoices: " . $invoices->count() . "\n";

foreach ($invoices as $invoice) {
    echo "\nInvoice: {$invoice->invoice_number} (Status: {$invoice->status->value})\n";
    echo "  Line Items: " . $invoice->lineItems->count() . "\n";

    // Check if invoice has transactions
    $transactions = Transaction::where('transactionable_type', Invoice::class)
        ->where('transactionable_id', $invoice->id)
        ->get();

    echo "  Transactions: " . $transactions->count() . "\n";

    if ($transactions->isEmpty()) {
        echo "  ⚠️  NO TRANSACTIONS CREATED FOR THIS INVOICE!\n";
    } else {
        foreach ($transactions as $transaction) {
            $journalEntries = JournalEntry::where('transaction_id', $transaction->id)->get();
            echo "    Transaction #{$transaction->id}: " . $journalEntries->count() . " journal entries\n";

            foreach ($journalEntries as $entry) {
                $account = $entry->account;
                $type = $entry->type->value ?? $entry->type;
                $amount = $entry->amount;
                $category = $account->category->value ?? $account->category;
                echo "      - {$account->name} ({$category}): {$type} {$amount}\n";
            }
        }
    }

    // Check inventory movements for this invoice
    $lineItemIds = $invoice->lineItems->pluck('id');
    $movements = InventoryMovement::where('reference_type', DocumentLineItem::class)
        ->whereIn('reference_id', $lineItemIds)
        ->get();

    echo "  Inventory Movements: " . $movements->count() . "\n";
}

// Check if inventory movements have related transactions
echo "\n=== INVENTORY MOVEMENTS vs TRANSACTIONS ===\n";
$allMovements = InventoryMovement::all();
echo "Total Inventory Movements: " . $allMovements->count() . "\n";

$purchaseMovements = $allMovements->filter(fn($m) => $m->movement_type->value === 'purchase');
$saleMovements = $allMovements->filter(fn($m) => $m->movement_type->value === 'sale');

echo "Purchase Movements: " . $purchaseMovements->count() . "\n";
echo "Sale Movements: " . $saleMovements->count() . "\n\n";

// Check if transactions exist for inventory accounts
$inventoryAccount = \App\Models\Accounting\Account::where('name', 'Inventory')->first();
$cogsAccount = \App\Models\Accounting\Account::where('name', 'Cost of Goods Sold')->first();

if ($inventoryAccount) {
    $inventoryEntries = JournalEntry::where('account_id', $inventoryAccount->id)->get();
    echo "Inventory Account Entries: " . $inventoryEntries->count() . "\n";

    $inventoryDebit = $inventoryEntries->where('type', 'debit')->sum('amount');
    $inventoryCredit = $inventoryEntries->where('type', 'credit')->sum('amount');
    $inventoryBalance = $inventoryDebit - $inventoryCredit;

    echo "  Debits: {$inventoryDebit}\n";
    echo "  Credits: {$inventoryCredit}\n";
    echo "  Balance: {$inventoryBalance}\n\n";
} else {
    echo "⚠️  INVENTORY ACCOUNT NOT FOUND!\n\n";
}

if ($cogsAccount) {
    $cogsEntries = JournalEntry::where('account_id', $cogsAccount->id)->get();
    echo "COGS Account Entries: " . $cogsEntries->count() . "\n";

    $cogsDebit = $cogsEntries->where('type', 'debit')->sum('amount');
    $cogsCredit = $cogsEntries->where('type', 'credit')->sum('amount');

    echo "  Debits: {$cogsDebit}\n";
    echo "  Credits: {$cogsCredit}\n";
    echo "  Total COGS: {$cogsDebit}\n\n";
} else {
    echo "⚠️  COGS ACCOUNT NOT FOUND!\n\n";
}

// Calculate expected inventory value
echo "=== EXPECTED vs ACTUAL INVENTORY VALUE ===\n";
$batches = \App\Models\Inventory\InventoryBatch::all();
$totalInventoryValue = $batches->sum(function ($batch) {
    return $batch->quantity_remaining * $batch->unit_cost;
});

echo "Inventory Value (from batches): {$totalInventoryValue}\n";
echo "Inventory Account Balance: " . ($inventoryBalance ?? 'N/A') . "\n";

if (isset($inventoryBalance) && $totalInventoryValue != $inventoryBalance) {
    echo "⚠️  MISMATCH! Inventory value in batches doesn't match accounting balance!\n";
    echo "   Difference: " . ($inventoryBalance - $totalInventoryValue) . "\n";
} elseif (isset($inventoryBalance)) {
    echo "✅ Match!\n";
}

echo "\n=== SUMMARY ===\n";
echo "Issue: If bills/invoices don't have transactions, then:\n";
echo "1. Bill observer may not be creating accounting transactions\n";
echo "2. Invoice observer may not be creating accounting transactions\n";
echo "3. Inventory movements are created, but not reflected in accounting\n";
echo "4. This causes mismatch between inventory stats and accounting reports\n";
