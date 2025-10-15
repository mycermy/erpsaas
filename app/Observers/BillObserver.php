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
        // NOTE: Don't process inventory here - line items don't exist yet!
        // Inventory will be processed via DocumentLineItemObserver when line items are added

        // Don't create accounting transaction here either - needs line items
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

            // After receiving stock, find any invoices that were flagged for this item
            // and clear the flag if sufficient stock now exists to cover their quantities.
            $flaggedInvoices = \App\Models\Accounting\Invoice::where('inventory_flagged', true)
                ->where('company_id', $bill->company_id)
                ->get();

            foreach ($flaggedInvoices as $invoice) {
                foreach ($invoice->lineItems as $invLine) {
                    if ($invLine->offering && $invLine->offering->inventoryItem && $invLine->offering->inventoryItem->id === $inventoryItem->id) {
                        // Check if we now have sufficient stock for this invoice line
                        if ($inventoryService->hasSufficientStock($inventoryItem, $warehouse, $invLine->quantity)) {
                            // Clear invoice flag and continue to next invoice
                            $invoice->clearInventoryFlag();

                            break 2;
                        }
                    }
                }
            }
        }
    }
}
