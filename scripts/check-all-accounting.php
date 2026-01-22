#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\Transaction;
use App\Models\Accounting\JournalEntry;

echo "=== ACCOUNTING TRANSACTIONS SUMMARY ===" . PHP_EOL . PHP_EOL;

// Bills
$billTrans = Transaction::where('transactionable_type', 'App\Models\Accounting\Bill')
    ->where('type', 'journal')
    ->with('journalEntries.account')
    ->get();

echo "BILLS:" . PHP_EOL;
echo "  Total transactions: " . $billTrans->count() . PHP_EOL;
$billJECount = 0;
foreach ($billTrans as $t) {
    $billJECount += $t->journalEntries->count();
}
echo "  Total journal entries: " . $billJECount . PHP_EOL;
if ($billTrans->count() > 0) {
    $firstBill = $billTrans->first();
    echo "  Sample - Trans #{$firstBill->id}: RM" . number_format($firstBill->amount / 100, 2) . PHP_EOL;
    foreach ($firstBill->journalEntries as $je) {
        echo "    {$je->type->value}: {$je->account->name} = RM" . number_format($je->amount / 100, 2) . PHP_EOL;
    }
}
echo PHP_EOL;

// Invoices
$invTrans = Transaction::where('transactionable_type', 'App\Models\Accounting\Invoice')
    ->where('type', 'journal')
    ->with('journalEntries.account')
    ->get();

echo "INVOICES:" . PHP_EOL;
echo "  Total transactions: " . $invTrans->count() . PHP_EOL;
$invJECount = 0;
foreach ($invTrans as $t) {
    $invJECount += $t->journalEntries->count();
}
echo "  Total journal entries: " . $invJECount . PHP_EOL;
if ($invTrans->count() > 0) {
    $firstInv = $invTrans->first();
    echo "  Sample - Trans #{$firstInv->id}: RM" . number_format($firstInv->amount / 100, 2) . PHP_EOL;
    foreach ($firstInv->journalEntries as $je) {
        echo "    {$je->type->value}: {$je->account->name} = RM" . number_format($je->amount / 100, 2) . PHP_EOL;
    }
}
echo PHP_EOL;

// Adjustments
$adjTrans = Transaction::where('transactionable_type', 'App\Models\Inventory\InventoryAdjustment')
    ->where('type', 'journal')
    ->with('journalEntries.account')
    ->get();

echo "INVENTORY ADJUSTMENTS:" . PHP_EOL;
echo "  Total transactions: " . $adjTrans->count() . PHP_EOL;
$adjJECount = 0;
foreach ($adjTrans as $t) {
    $adjJECount += $t->journalEntries->count();
}
echo "  Total journal entries: " . $adjJECount . PHP_EOL;
if ($adjTrans->count() > 0) {
    $firstAdj = $adjTrans->first();
    echo "  Sample - Trans #{$firstAdj->id}: RM" . number_format($firstAdj->amount / 100, 2) . PHP_EOL;
    foreach ($firstAdj->journalEntries as $je) {
        echo "    {$je->type->value}: {$je->account->name} = RM" . number_format($je->amount / 100, 2) . PHP_EOL;
    }
}
echo PHP_EOL;

// Summary
echo "=== SUMMARY ===" . PHP_EOL;
echo "Total accounting transactions: " . ($billTrans->count() + $invTrans->count() + $adjTrans->count()) . PHP_EOL;
echo "Total journal entries: " . ($billJECount + $invJECount + $adjJECount) . PHP_EOL;
