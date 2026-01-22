#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Inventory\InventoryMovement;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Account;

echo "=== INVENTORY ACCOUNTING RECONCILIATION ===" . PHP_EOL . PHP_EOL;

// Get inventory account
$inventoryAccount = Account::where('type', 'current_asset')
    ->where('name', 'LIKE', '%Inventory%')
    ->first();

if (!$inventoryAccount) {
    echo "ERROR: Inventory account not found!" . PHP_EOL;
    exit(1);
}

echo "Inventory Account: {$inventoryAccount->name} (ID: {$inventoryAccount->id})" . PHP_EOL . PHP_EOL;

// Calculate inventory value from movements
$movements = InventoryMovement::with('inventoryItem')->get();
$inventoryValueFromMovements = 0;

echo "=== INVENTORY MOVEMENTS ===" . PHP_EOL;
foreach ($movements->groupBy('movement_type') as $type => $typeMovements) {
    $typeValue = $typeMovements->sum(function ($m) {
        return $m->quantity * $m->unit_cost;
    });

    echo "{$type}: {$typeMovements->count()} movements, Total Value: RM" . number_format($typeValue / 100, 2) . PHP_EOL;

    // For inventory value calculation
    if (in_array($type, ['purchase', 'initial', 'return'])) {
        $inventoryValueFromMovements += $typeValue;
    } else {
        $inventoryValueFromMovements -= abs($typeValue);
    }
}

echo PHP_EOL;
echo "CALCULATED INVENTORY VALUE FROM MOVEMENTS: RM" . number_format($inventoryValueFromMovements / 100, 2) . PHP_EOL;
echo PHP_EOL;

// Get accounting inventory value (debits - credits)
$journalEntries = JournalEntry::where('account_id', $inventoryAccount->id)
    ->with('transaction.transactionable')
    ->get();

echo "=== ACCOUNTING JOURNAL ENTRIES (Inventory Account) ===" . PHP_EOL;

$debits = 0;
$credits = 0;

foreach ($journalEntries->groupBy('transaction.transactionable_type') as $type => $entries) {
    $typeDebits = $entries->where('type', 'debit')->sum('amount');
    $typeCredits = $entries->where('type', 'credit')->sum('amount');

    $typeName = class_basename($type);
    echo "{$typeName}:" . PHP_EOL;
    echo "  Debits: RM" . number_format($typeDebits / 100, 2) . " ({$entries->where('type', 'debit')->count()} entries)" . PHP_EOL;
    echo "  Credits: RM" . number_format($typeCredits / 100, 2) . " ({$entries->where('type', 'credit')->count()} entries)" . PHP_EOL;
    echo "  Net: RM" . number_format(($typeDebits - $typeCredits) / 100, 2) . PHP_EOL;
    echo PHP_EOL;

    $debits += $typeDebits;
    $credits += $typeCredits;
}

$accountingInventoryValue = $debits - $credits;

echo "TOTAL DEBITS: RM" . number_format($debits / 100, 2) . PHP_EOL;
echo "TOTAL CREDITS: RM" . number_format($credits / 100, 2) . PHP_EOL;
echo "ACCOUNTING INVENTORY VALUE: RM" . number_format($accountingInventoryValue / 100, 2) . PHP_EOL;
echo PHP_EOL;

// Compare
$difference = $inventoryValueFromMovements - $accountingInventoryValue;

echo "=== COMPARISON ===" . PHP_EOL;
echo "Inventory from Movements: RM" . number_format($inventoryValueFromMovements / 100, 2) . PHP_EOL;
echo "Inventory from Accounting: RM" . number_format($accountingInventoryValue / 100, 2) . PHP_EOL;
echo "Difference: RM" . number_format($difference / 100, 2) . PHP_EOL;
echo PHP_EOL;

if (abs($difference) > 1) { // Allow 1 cent rounding difference
    echo "❌ MISMATCH DETECTED!" . PHP_EOL;
    echo "Investigating..." . PHP_EOL . PHP_EOL;

    // Check which transactions might be missing accounting entries
    echo "=== MOVEMENTS WITHOUT ACCOUNTING ===" . PHP_EOL;

    $movementsByRef = $movements->groupBy(function ($m) {
        return $m->reference_type . ':' . $m->reference_id;
    });

    foreach ($movementsByRef as $ref => $refMovements) {
        [$refType, $refId] = explode(':', $ref);

        // Check if accounting exists for this reference
        $hasAccounting = JournalEntry::join('transactions', 'journal_entries.transaction_id', '=', 'transactions.id')
            ->where('transactions.transactionable_type', $refType)
            ->where('transactions.transactionable_id', $refId)
            ->where('journal_entries.account_id', $inventoryAccount->id)
            ->exists();

        if (!$hasAccounting) {
            $totalValue = $refMovements->sum(function ($m) {
                return $m->quantity * $m->unit_cost;
            });
            echo "Missing: " . class_basename($refType) . " #{$refId}" . PHP_EOL;
            echo "  Movements: {$refMovements->count()}" . PHP_EOL;
            echo "  Value: RM" . number_format($totalValue / 100, 2) . PHP_EOL;
            echo PHP_EOL;
        }
    }
} else {
    echo "✅ MATCH! Inventory movements and accounting are in sync." . PHP_EOL;
}
