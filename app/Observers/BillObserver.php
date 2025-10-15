<?php

namespace App\Observers;

use App\Enums\Accounting\BillStatus;
use App\Enums\Inventory\MovementType;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Transaction;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class BillObserver
{
    public function created(Bill $bill): void
    {
        // Handle inventory inbound when bill is created (goods received)
        if ($bill->status !== BillStatus::Void) {
            $this->processInventoryInbound($bill);
        }

        // $bill->createInitialTransaction();
    }

    public function saving(Bill $bill): void
    {
        // Check if status changed from Open/Partial/Paid to Overdue
        $previousStatus = $bill->getOriginal('status');
        $isOverdue = $bill->shouldBeOverdue();

        if ($isOverdue && $previousStatus !== BillStatus::Overdue) {
            $bill->status = BillStatus::Overdue;
        }
    }

    /**
     * Handle the Bill "deleted" event.
     */
    public function deleted(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });

            $bill->transactions()->each(function (Transaction $transaction) {
                $transaction->delete();
            });
        });
    }

    /**
     * Process inventory inbound movements when bill is paid
     */
    public function processInventoryInbound(Bill $bill): void
    {
        $inventoryService = app(InventoryService::class);

        foreach ($bill->lineItems as $lineItem) {
            // Only process if offering has an inventory item (stockable)
            if (! $lineItem->offering) {
                continue;
            }

            $inventoryItem = $lineItem->offering->inventoryItem;

            if (! $inventoryItem) {
                continue; // Not a stockable item
            }

            // Use bill's vendor address or default to first warehouse
            $warehouse = \App\Models\Inventory\Warehouse::where('company_id', $bill->company_id)
                ->where('active', true)
                ->where('is_default', true)
                ->first();

            if (! $warehouse) {
                continue;
            }

            // Record purchase movement (will auto-create batch if item tracks batches)
            $inventoryService->recordMovement(
                item: $inventoryItem,
                warehouse: $warehouse,
                quantity: $lineItem->quantity,
                movementType: MovementType::Purchase,
                unitCost: $lineItem->unit_price,
                movementDate: $bill->date,
                referenceType: Bill::class,
                referenceId: $bill->id,
                notes: "Purchase from Bill #{$bill->bill_number}"
            );
        }
    }
}
