<?php

namespace Modules\Inventory\Observers;

use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\JournalEntryType;
use App\Enums\Accounting\TransactionType;
use Modules\Inventory\Enums\AdjustmentStatus;
use Modules\Inventory\Enums\AdjustmentType;
use Modules\Inventory\Enums\MovementType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Transaction;
use Modules\Inventory\Models\InventoryAdjustment;
use Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentObserver
{
    /**
     * Handle the InventoryAdjustment "saving" event.
     * Process inventory movements when adjustment is approved.
     */
    public function saved(InventoryAdjustment $adjustment): void
    {
        // Only process when status changes to Approved
        $wasNotApproved = $adjustment->getOriginal('status') !== AdjustmentStatus::Approved;
        $isApproved = $adjustment->status === AdjustmentStatus::Approved;

        \Illuminate\Support\Facades\Log::info('AdjustmentObserver triggered', [
            'adjustment_id' => $adjustment->id,
            'was_not_approved' => $wasNotApproved,
            'is_approved' => $isApproved,
            'adjustment_type' => $adjustment->adjustment_type->value ?? 'null',
        ]);

        // We only want to process adjustments that transitioned to Approved
        if ($wasNotApproved && $isApproved) {
            \Illuminate\Support\Facades\Log::info('Processing adjustment', ['adjustment_id' => $adjustment->id]);
            $this->processInventoryAdjustment($adjustment);
            $this->createAccountingTransaction($adjustment);
        } else {
            \Illuminate\Support\Facades\Log::info('Skipping adjustment processing', [
                'adjustment_id' => $adjustment->id,
                'was_not_approved' => $wasNotApproved,
                'is_approved' => $isApproved
            ]);
        }
    }

    /**
     * Process inventory movements for approved adjustment
     */
    protected function processInventoryAdjustment(InventoryAdjustment $adjustment): void
    {
        \Illuminate\Support\Facades\Log::info('processInventoryAdjustment started', ['adjustment_id' => $adjustment->id]);

        // Load items without global scope since scope may not work in observer context
        $items = \Modules\Inventory\Models\InventoryAdjustmentItem::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('adjustment_id', $adjustment->id)
            ->with(['batchAllocations' => function ($query) {
                $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
            }])
            ->get();

        \Illuminate\Support\Facades\Log::info('Loaded adjustment items', [
            'adjustment_id' => $adjustment->id,
            'items_count' => $items->count()
        ]);

        $adjustment->setRelation('items', $items);
        $adjustment->load(['warehouse' => function ($query) {
            $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
        }]);
        $inventoryService = app(InventoryService::class);
        foreach ($adjustment->items as $adjustmentItem) {
            \Illuminate\Support\Facades\Log::info('Processing adjustment item', [
                'item_id' => $adjustmentItem->id,
                'inventory_item_id' => $adjustmentItem->inventory_item_id,
                'quantity_adjusted' => $adjustmentItem->quantity_adjusted
            ]);

            $inventoryItem = \Modules\Inventory\Models\InventoryItem::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)->find($adjustmentItem->inventory_item_id);

            if (! $inventoryItem) {
                continue;
            }

            // Prefer the explicit quantity_adjusted field. Fall back to computed delta if absent.
            $quantity = $adjustmentItem->quantity_adjusted ?? null;
            if (is_null($quantity)) {
                $quantity = ($adjustmentItem->quantity_after ?? 0) - ($adjustmentItem->quantity_before ?? 0);
            }

            // Skip zero adjustments
            if ((int) $quantity === 0) {
                continue;
            }

            $notes = $adjustmentItem->reason ?? $adjustment->reason ?? 'Inventory adjustment';

            // Ensure movement_date is a DateTime (InventoryService accepts DateTime/Carbon)
            $movementDate = $adjustment->adjustment_date;

            // Determine movement type based on adjustment reason
            $movementType = $this->getMovementTypeForAdjustment($adjustment);

            // Record adjustment movement
            $batchAllocations = $adjustmentItem->batchAllocations()->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)->get()->map(function ($batch) {
                return [
                    'batch_id' => $batch->inventory_batch_id,
                    'quantity' => $batch->quantity,
                    'unit_cost' => $batch->unit_cost,
                    'total_cost' => $batch->total_cost,
                ];
            })->toArray();

            \Illuminate\Support\Facades\Log::info('About to record movement', [
                'adjustment_id' => $adjustment->id,
                'item_id' => $adjustmentItem->id,
                'quantity' => $quantity,
                'movement_type' => $movementType->value,
                'unit_cost' => $adjustmentItem->unit_cost ?? 0
            ]);

            try {
                $inventoryService->recordMovement(
                    item: $inventoryItem,
                    warehouse: $adjustment->warehouse,
                    quantity: $quantity, // positive = increase, negative = decrease
                    movementType: $movementType,
                    unitCost: $adjustmentItem->unit_cost ?? 0, // Use the specified unit cost for adjustments
                    referenceType: InventoryAdjustment::class,
                    referenceId: $adjustment->id,
                    notes: $notes,
                    movementDate: $movementDate,
                    createdBy: $adjustment->created_by ?? null,
                    adjustmentType: $adjustment->adjustment_type,
                    batchAllocations: $batchAllocations
                );

                \Illuminate\Support\Facades\Log::info('Movement recorded successfully', [
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $adjustmentItem->id
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to record movement', [
                    'adjustment_id' => $adjustment->id,
                    'item_id' => $adjustmentItem->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Determine the appropriate movement type for an adjustment
     */
    protected function getMovementTypeForAdjustment(InventoryAdjustment $adjustment): MovementType
    {
        $reason = strtolower($adjustment->reason ?? '');

        // Check for initial stock setup
        if (str_contains($reason, 'initial') || str_contains($reason, 'setup')) {
            return MovementType::Initial;
        }

        // Check for damage/write-off
        if (str_contains($reason, 'damage') || str_contains($reason, 'write-off') || str_contains($reason, 'loss')) {
            return MovementType::Adjustment; // Keep as ADJ for damage
        }

        // Default to adjustment for other cases
        return MovementType::Adjustment;
    }

    /**
     * Create accounting transaction (journal entry) for inventory adjustment
     */
    protected function createAccountingTransaction(InventoryAdjustment $adjustment): void
    {
        \Illuminate\Support\Facades\Log::info('Creating accounting transaction', ['adjustment_id' => $adjustment->id]);

        // Skip if transaction already exists
        $existingTransaction = Transaction::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('transactionable_type', InventoryAdjustment::class)
            ->where('transactionable_id', $adjustment->id)
            ->exists();

        if ($existingTransaction) {
            \Illuminate\Support\Facades\Log::info('Accounting transaction already exists', ['adjustment_id' => $adjustment->id]);
            return;
        }

        // Load items to calculate total value
        $items = \Modules\Inventory\Models\InventoryAdjustmentItem::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('adjustment_id', $adjustment->id)
            ->with(['inventoryItem' => function ($query) {
                $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
            }])
            ->get();

        $totalValue = 0;
        $isIncrease = false;

        foreach ($items as $item) {
            $inventoryItem = $item->inventoryItem;
            if (!$inventoryItem) {
                continue;
            }

            $quantity = $item->quantity_adjusted ?? 0;
            $unitCost = $item->unit_cost ?? 0;

            // If unit cost is 0 (e.g., damage adjustments), get cost from related movement
            if ($unitCost == 0 && $quantity != 0) {
                $movement = \Modules\Inventory\Models\InventoryMovement::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                    ->where('reference_type', InventoryAdjustment::class)
                    ->where('reference_id', $adjustment->id)
                    ->where('inventory_item_id', $inventoryItem->id)
                    ->first();

                if ($movement) {
                    $unitCost = $movement->unit_cost ?? 0;
                }
            }

            $itemValue = abs($quantity * $unitCost);
            $totalValue += $itemValue;

            if ($quantity > 0) {
                $isIncrease = true;
            }
        }

        // Skip if no value to record
        if ($totalValue == 0) {
            \Illuminate\Support\Facades\Log::info('No value to record', ['adjustment_id' => $adjustment->id]);
            return;
        }

        // Get the inventory asset account (from first item with inventory item)
        $inventoryAccount = null;
        foreach ($items as $item) {
            if ($item->inventoryItem && $item->inventoryItem->asset_account_id) {
                $inventoryAccount = Account::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                    ->find($item->inventoryItem->asset_account_id);
                break;
            }
        }

        if (!$inventoryAccount) {
            \Illuminate\Support\Facades\Log::warning('No inventory asset account found', ['adjustment_id' => $adjustment->id]);
            return;
        }

        // Determine contra account based on adjustment type
        $contraAccount = $this->getContraAccountForAdjustment($adjustment, $inventoryAccount->company_id);

        if (!$contraAccount) {
            \Illuminate\Support\Facades\Log::warning('No contra account found', ['adjustment_id' => $adjustment->id]);
            return;
        }

        // Create transaction in a database transaction
        DB::transaction(function () use ($adjustment, $totalValue, $inventoryAccount, $contraAccount, $isIncrease) {
            $transaction = Transaction::create([
                'company_id' => $adjustment->company_id,
                'type' => TransactionType::Journal,
                'description' => $this->getTransactionDescription($adjustment),
                'notes' => $adjustment->reason,
                'amount' => $totalValue,
                'posted_at' => $adjustment->adjustment_date,
                'transactionable_type' => InventoryAdjustment::class,
                'transactionable_id' => $adjustment->id,
                'created_by' => $adjustment->created_by,
            ]);

            \Illuminate\Support\Facades\Log::info('Transaction created', [
                'adjustment_id' => $adjustment->id,
                'transaction_id' => $transaction->id,
                'amount' => $totalValue
            ]);

            // For Journal type transactions, we must create journal entries manually
            // Determine debit and credit accounts based on adjustment type
            if ($isIncrease || $adjustment->adjustment_type === AdjustmentType::Stocktake) {
                // Initial stock or stocktake increase: Debit Inventory, Credit Equity
                $debitAccount = $inventoryAccount;
                $creditAccount = $contraAccount;
            } else {
                // Damage/loss: Debit Expense, Credit Inventory
                $debitAccount = $contraAccount;
                $creditAccount = $inventoryAccount;
            }

            // Create debit entry
            $debitAccount->journalEntries()->create([
                'company_id' => $adjustment->company_id,
                'transaction_id' => $transaction->id,
                'type' => JournalEntryType::Debit,
                'amount' => $totalValue,
                'description' => $transaction->description,
                'created_by' => $adjustment->created_by,
            ]);

            // Create credit entry
            $creditAccount->journalEntries()->create([
                'company_id' => $adjustment->company_id,
                'transaction_id' => $transaction->id,
                'type' => JournalEntryType::Credit,
                'amount' => $totalValue,
                'description' => $transaction->description,
                'created_by' => $adjustment->created_by,
            ]);

            \Illuminate\Support\Facades\Log::info('Journal entries created', [
                'adjustment_id' => $adjustment->id,
                'transaction_id' => $transaction->id,
                'debit_account' => $debitAccount->name,
                'credit_account' => $creditAccount->name
            ]);
        });
    }

    /**
     * Get contra account for the adjustment type
     */
    protected function getContraAccountForAdjustment(InventoryAdjustment $adjustment, int $companyId): ?Account
    {
        $reason = strtolower($adjustment->reason ?? '');

        // For initial stock, use Opening Balance Equity or any equity account
        if (str_contains($reason, 'initial') || str_contains($reason, 'setup') || $adjustment->adjustment_type === AdjustmentType::Stocktake) {
            // Try to find specific equity accounts first
            $account = Account::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                ->where('company_id', $companyId)
                ->where('type', AccountType::Equity)
                ->where(function ($query) {
                    $query->where('name', 'LIKE', '%Opening Balance%')
                        ->orWhere('name', 'LIKE', '%Owner%')
                        ->orWhere('name', 'LIKE', '%Retained Earnings%');
                })
                ->first();

            // Fall back to any equity account
            if (!$account) {
                $account = Account::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                    ->where('company_id', $companyId)
                    ->where('type', AccountType::Equity)
                    ->first();
            }

            return $account;
        }

        // For damage/loss, use expense account
        if (str_contains($reason, 'damage') || str_contains($reason, 'loss') || $adjustment->adjustment_type === AdjustmentType::Damage) {
            // Try to find inventory loss/shrinkage expense account
            $account = Account::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                ->where('company_id', $companyId)
                ->where('type', AccountType::OperatingExpense)
                ->where(function ($query) {
                    $query->where('name', 'LIKE', '%Inventory Loss%')
                        ->orWhere('name', 'LIKE', '%Shrinkage%')
                        ->orWhere('name', 'LIKE', '%Damage%');
                })
                ->first();

            // Fall back to COGS if no specific account exists
            if (!$account) {
                $account = Account::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                    ->where('company_id', $companyId)
                    ->where('type', AccountType::OperatingExpense)
                    ->where('name', 'LIKE', '%Cost of Goods Sold%')
                    ->first();
            }

            return $account;
        }

        return null;
    }

    /**
     * Get transaction description based on adjustment
     */
    protected function getTransactionDescription(InventoryAdjustment $adjustment): string
    {
        $type = $adjustment->adjustment_type?->label() ?? 'Adjustment';
        $number = $adjustment->adjustment_number ?? $adjustment->id;

        return "{$type} - {$number}";
    }
}
