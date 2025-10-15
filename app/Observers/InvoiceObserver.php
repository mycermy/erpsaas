<?php

namespace App\Observers;

use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Inventory\MovementType;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

class InvoiceObserver
{
    public function saving(Invoice $invoice): void
    {
        // Handle inventory outbound when invoice is approved/sent (not draft)
        // In real business: goods ship when invoice is created/approved, not when paid
        $wasDraft = $invoice->getOriginal('status') === InvoiceStatus::Draft;
        $isNoLongerDraft = $invoice->status !== InvoiceStatus::Draft && $invoice->status !== InvoiceStatus::Void;

        if ($wasDraft && $isNoLongerDraft) {
            $this->processInventoryOutbound($invoice);
        }

        if (! $invoice->wasApproved()) {
            return;
        }

        if ($invoice->isDirty('due_date') && $invoice->status === InvoiceStatus::Overdue && ! $invoice->shouldBeOverdue() && ! $invoice->hasPayments()) {
            $invoice->status = $invoice->hasBeenSent() ? InvoiceStatus::Sent : InvoiceStatus::Unsent;

            return;
        }

        if ($invoice->shouldBeOverdue()) {
            $invoice->status = InvoiceStatus::Overdue;
        }
    }

    public function deleted(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice->lineItems()->each(function (DocumentLineItem $lineItem) {
                $lineItem->delete();
            });

            $invoice->transactions()->each(function (Transaction $transaction) {
                $transaction->delete();
            });
        });
    }

    /**
     * Process inventory outbound movements when invoice is paid
     */
    public function processInventoryOutbound(Invoice $invoice): void
    {
        $inventoryService = app(InventoryService::class);

        foreach ($invoice->lineItems as $lineItem) {
            // Only process if offering has an inventory item (stockable)
            if (! $lineItem->offering) {
                continue;
            }

            $inventoryItem = $lineItem->offering->inventoryItem;

            if (! $inventoryItem) {
                continue; // Not a stockable item
            }

            // Use invoice's default warehouse
            $warehouse = \App\Models\Inventory\Warehouse::where('company_id', $invoice->company_id)
                ->where('active', true)
                ->where('is_default', true)
                ->first();

            if (! $warehouse) {
                continue;
            }

            // Record sale movement (negative quantity)
            $inventoryService->recordMovement(
                item: $inventoryItem,
                warehouse: $warehouse,
                quantity: -abs($lineItem->quantity), // Ensure negative
                movementType: MovementType::Sale,
                unitCost: 0, // COGS will be calculated by InventoryService
                movementDate: $invoice->date,
                referenceType: Invoice::class,
                referenceId: $invoice->id,
                notes: "Sale from Invoice #{$invoice->invoice_number}"
            );
        }
    }
}
