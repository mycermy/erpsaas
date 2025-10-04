<?php

namespace App\Services\Inventory;

use App\Enums\Accounting\TransactionType;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Transaction;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;

class COGSService
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Record a sale and create COGS journal entries
     */
    public function recordSale(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity,
        Transaction $salesTransaction,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): array {
        return DB::transaction(function () use (
            $item,
            $warehouse,
            $quantity,
            $salesTransaction,
            $referenceType,
            $referenceId
        ) {
            // Calculate COGS using FIFO/LIFO/Average
            $cogsCalculation = $this->inventoryService->calculateCOGS($item, $warehouse, $quantity);

            // Record inventory movement (outbound)
            $movement = $this->inventoryService->recordMovement(
                item: $item,
                warehouse: $warehouse,
                quantity: -$quantity, // Negative for outbound
                movementType: \App\Enums\Inventory\MovementType::Sale,
                unitCost: isset($cogsCalculation['average_cost'])
                    ? $cogsCalculation['average_cost']
                    : (int) round($cogsCalculation['total_cost'] / $quantity),
                referenceType: $referenceType,
                referenceId: $referenceId,
                transactionId: $salesTransaction->id,
                movementDate: $salesTransaction->posted_at
            );

            // Reduce batch quantities if using FIFO/LIFO
            if (isset($cogsCalculation['batches']) && ! empty($cogsCalculation['batches'])) {
                $this->inventoryService->reduceBatches($cogsCalculation['batches']);
            }

            // Create COGS journal entries
            $cogsTransaction = $this->createCOGSTransaction(
                $item,
                $cogsCalculation['total_cost'],
                $salesTransaction->posted_at,
                $referenceType,
                $referenceId
            );

            return [
                'movement' => $movement,
                'cogs_transaction' => $cogsTransaction,
                'cogs_amount' => $cogsCalculation['total_cost'],
                'batches_used' => $cogsCalculation['batches'] ?? [],
            ];
        });
    }

    /**
     * Record a purchase and create inventory asset entries
     */
    public function recordPurchase(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity,
        int $unitCost,
        Transaction $purchaseTransaction,
        ?int $billId = null
    ): InventoryMovement {
        return DB::transaction(function () use (
            $item,
            $warehouse,
            $quantity,
            $unitCost,
            $purchaseTransaction,
            $billId
        ) {
            // Record inventory movement (inbound)
            $movement = $this->inventoryService->recordMovement(
                item: $item,
                warehouse: $warehouse,
                quantity: $quantity,
                movementType: \App\Enums\Inventory\MovementType::Purchase,
                unitCost: $unitCost,
                referenceType: 'App\Models\Accounting\Bill',
                referenceId: $billId,
                transactionId: $purchaseTransaction->id,
                movementDate: $purchaseTransaction->posted_at
            );

            return $movement;
        });
    }

    /**
     * Create COGS transaction with journal entries
     */
    protected function createCOGSTransaction(
        InventoryItem $item,
        int $cogsAmount,
        \DateTime $transactionDate,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): Transaction {
        if ($cogsAmount <= 0) {
            throw new \InvalidArgumentException('COGS amount must be greater than zero');
        }

        if (! $item->cogs_account_id || ! $item->inventory_account_id) {
            throw new \InvalidArgumentException('COGS and Inventory accounts must be configured for this item');
        }

        // Create transaction
        $transaction = Transaction::create([
            'company_id' => $item->company_id,
            'type' => TransactionType::Journal,
            'description' => "COGS for {$item->offering->name}",
            'amount' => $cogsAmount,
            'posted_at' => $transactionDate,
        ]);

        // Debit: Cost of Goods Sold (Expense)
        JournalEntry::create([
            'company_id' => $item->company_id,
            'transaction_id' => $transaction->id,
            'account_id' => $item->cogs_account_id,
            'type' => 'debit',
            'amount' => $cogsAmount,
            'description' => "COGS - {$item->offering->name}",
        ]);

        // Credit: Inventory (Asset)
        JournalEntry::create([
            'company_id' => $item->company_id,
            'transaction_id' => $transaction->id,
            'account_id' => $item->inventory_account_id,
            'type' => 'credit',
            'amount' => $cogsAmount,
            'description' => "Inventory reduction - {$item->offering->name}",
        ]);

        return $transaction;
    }

    /**
     * Reverse COGS for a return
     */
    public function reverseCogsForReturn(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity,
        int $originalCogs,
        Transaction $returnTransaction
    ): void {
        DB::transaction(function () use ($item, $warehouse, $quantity, $originalCogs, $returnTransaction) {
            // Record return movement (inbound)
            $this->inventoryService->recordMovement(
                item: $item,
                warehouse: $warehouse,
                quantity: $quantity,
                movementType: \App\Enums\Inventory\MovementType::Return,
                unitCost: (int) round($originalCogs / $quantity),
                transactionId: $returnTransaction->id,
                movementDate: $returnTransaction->posted_at
            );

            // Create reversing COGS entries
            $transaction = Transaction::create([
                'company_id' => $item->company_id,
                'type' => TransactionType::Journal,
                'description' => "COGS Reversal for {$item->offering->name}",
                'amount' => $originalCogs,
                'posted_at' => $returnTransaction->posted_at,
            ]);

            // Debit: Inventory (Asset) - increase back
            JournalEntry::create([
                'company_id' => $item->company_id,
                'transaction_id' => $transaction->id,
                'account_id' => $item->inventory_account_id,
                'type' => 'debit',
                'amount' => $originalCogs,
                'description' => "Inventory return - {$item->offering->name}",
            ]);

            // Credit: Cost of Goods Sold (Expense) - reduce
            JournalEntry::create([
                'company_id' => $item->company_id,
                'transaction_id' => $transaction->id,
                'account_id' => $item->cogs_account_id,
                'type' => 'credit',
                'amount' => $originalCogs,
                'description' => "COGS reversal - {$item->offering->name}",
            ]);
        });
    }
}
