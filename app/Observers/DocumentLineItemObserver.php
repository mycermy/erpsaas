<?php

namespace App\Observers;

use App\Models\Accounting\DocumentLineItem;
use Illuminate\Support\Facades\Log;
use Zrm\Inventory\Enums\MovementType;
use Zrm\Inventory\Models\InventoryItem;
use Zrm\Inventory\Models\Warehouse;
use Zrm\Inventory\Services\InventoryService;

class DocumentLineItemObserver
{
    // Remove constructor dependency injection - use app() helper instead
    // This ensures the observer can be instantiated by Laravel's #[ObservedBy] attribute

    /**
     * Handle the DocumentLineItem "created" event.
     */
    public function created(DocumentLineItem $documentLineItem): void
    {
        // Use error_log for CLI visibility
        error_log("=== DocumentLineItemObserver::created FIRED - Line Item #{$documentLineItem->id} ===");

        try {
            Log::info('=== DocumentLineItemObserver::created FIRED ===', [
                'line_item_id' => $documentLineItem->id,
                'offering_id' => $documentLineItem->offering_id,
                'quantity' => $documentLineItem->quantity,
                'documentable_type' => $documentLineItem->documentable_type,
                'documentable_id' => $documentLineItem->documentable_id,
            ]);

            $this->processInventoryMovement($documentLineItem);

            Log::info('=== DocumentLineItemObserver::created COMPLETED ===');
            error_log('=== DocumentLineItemObserver::created COMPLETED ===');
        } catch (\Exception $e) {
            error_log("=== DocumentLineItemObserver EXCEPTION: {$e->getMessage()} ===");
            Log::error('DocumentLineItemObserver::created EXCEPTION', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle the DocumentLineItem "updated" event.
     */
    public function updated(DocumentLineItem $documentLineItem): void
    {
        $this->processInventoryMovement($documentLineItem);
    }

    /**
     * Process inventory movements when document is approved/paid
     */
    protected function processInventoryMovement(DocumentLineItem $lineItem): void
    {
        Log::info('processInventoryMovement called', [
            'line_item_id' => $lineItem->id,
        ]);

        // Only process if the document is approved/paid and item has inventory tracking
        if (! $this->shouldProcessInventory($lineItem)) {
            Log::info('shouldProcessInventory returned false', [
                'line_item_id' => $lineItem->id,
            ]);

            return;
        }

        $document = $lineItem->documentable; // Use documentable morphTo relationship

        Log::info('Processing inventory movement', [
            'line_item_id' => $lineItem->id,
            'document_class' => get_class($document),
            'document_id' => $document->id,
        ]);

        try {
            // Check document type using instanceof instead of ->type property
            if ($document instanceof \App\Models\Accounting\Invoice) {
                $this->handleInvoiceLineItem($lineItem);
            } elseif ($document instanceof \App\Models\Accounting\Bill) {
                $this->handleBillLineItem($lineItem);
            }
        } catch (\Exception $e) {
            Log::error('Inventory integration error', [
                'document_class' => get_class($document),
                'document_id' => $document->id,
                'line_item_id' => $lineItem->id,
                'error' => $e->getMessage(),
            ]);

            // Don't throw to prevent blocking the document workflow
            // Log for manual review instead
        }
    }

    protected function shouldProcessInventory(DocumentLineItem $lineItem): bool
    {
        Log::info('shouldProcessInventory check', [
            'line_item_id' => $lineItem->id,
            'has_offering' => ! is_null($lineItem->offering),
            'has_inventory_item' => ! is_null($lineItem->offering?->inventoryItem),
            'inventory_enabled' => $lineItem->offering?->inventoryItem?->isInventoryEnabled(),
        ]);

        // Check if offering has inventory tracking enabled
        if (! $lineItem->offering?->inventoryItem?->isInventoryEnabled()) {
            return false;
        }

        $document = $lineItem->documentable; // Use documentable morphTo relationship

        Log::info('shouldProcessInventory document check', [
            'line_item_id' => $lineItem->id,
            'has_document' => ! is_null($document),
            'document_class' => $document ? get_class($document) : 'null',
            'has_status' => isset($document->status),
            'status_value' => $document->status ?? 'null',
        ]);

        // Defensive: ensure document and status exist
        if (! $document || ! isset($document->status) || ! $document->status) {
            return false;
        }

        // For bills: process immediately (goods received)
        // For invoices: only process when approved/sent/paid
        if ($document instanceof \App\Models\Accounting\Bill) {
            $result = $document->status !== \App\Enums\Accounting\BillStatus::Void;
            Log::info('Bill processing decision', [
                'line_item_id' => $lineItem->id,
                'status' => $document->status->value,
                'will_process' => $result,
            ]);

            return $result;
        }

        if ($document instanceof \App\Models\Accounting\Invoice) {
            $result = in_array($document->status->value, ['sent', 'unsent', 'viewed', 'approved', 'partial', 'paid', 'overdue']);
            Log::info('Invoice processing decision', [
                'line_item_id' => $lineItem->id,
                'status' => $document->status->value,
                'will_process' => $result,
            ]);

            return $result;
        }

        return false;
    }

    protected function handleInvoiceLineItem(DocumentLineItem $lineItem): void
    {
        $document = $lineItem->documentable; // Use documentable
        $inventoryItem = $lineItem->offering->inventoryItem;

        // Check if already processed
        $existingMovement = $inventoryItem->movements()
            ->where('reference_type', get_class($document))
            ->where('reference_id', $document->id)
            ->where('movement_type', MovementType::Sale)
            ->exists();

        if ($existingMovement) {
            return; // Already processed
        }

        // Determine warehouse (use default or first available)
        $stockLevel = $inventoryItem->stockLevels()->first();
        if (! $stockLevel) {
            Log::warning('No stock level found for invoice line item', [
                'invoice_id' => $document->id,
                'offering_id' => $lineItem->offering_id,
            ]);

            return;
        }

        $warehouse = $stockLevel->warehouse;

        // Calculate COGS using inventory service
        $inventoryService = app(InventoryService::class);
        $cogsCalculation = $inventoryService->calculateCOGS(
            $inventoryItem,
            $warehouse,
            $lineItem->quantity
        );

        // Record inventory movement (outbound)
        $movement = $inventoryService->recordMovement(
            item: $inventoryItem,
            warehouse: $warehouse,
            quantity: -$lineItem->quantity, // Negative for outbound
            movementType: MovementType::Sale,
            unitCost: isset($cogsCalculation['average_cost'])
                ? $cogsCalculation['average_cost']
                : (int) round($cogsCalculation['total_cost'] / $lineItem->quantity),
            referenceType: get_class($document),
            referenceId: $document->id,
            movementDate: $document->issued_at
        );

        // Reduce batch quantities if using FIFO/LIFO
        if (isset($cogsCalculation['batches']) && ! empty($cogsCalculation['batches'])) {
            $inventoryService->reduceBatches($cogsCalculation['batches']);
        }

        Log::info('Invoice COGS recorded', [
            'invoice_number' => $document->document_number,
            'item' => $lineItem->offering->name,
            'quantity' => $lineItem->quantity,
            'cogs_amount' => $cogsCalculation['total_cost'],
        ]);
    }

    protected function handleBillLineItem(DocumentLineItem $lineItem): void
    {
        $document = $lineItem->documentable; // Use documentable
        $inventoryItem = $lineItem->offering->inventoryItem;

        // Check if already processed
        $existingMovement = $inventoryItem->movements()
            ->where('reference_type', get_class($document))
            ->where('reference_id', $document->id)
            ->where('movement_type', MovementType::Purchase)
            ->exists();

        if ($existingMovement) {
            return; // Already processed
        }

        // Determine warehouse: get default warehouse for the company
        $warehouse = Warehouse::where('company_id', $document->company_id)
            ->where('active', true)
            ->where('is_default', true)
            ->first();

        if (! $warehouse) {
            // If no default, get any active warehouse
            $warehouse = Warehouse::where('company_id', $document->company_id)
                ->where('active', true)
                ->first();
        }

        if (! $warehouse) {
            Log::warning('No warehouse found for bill line item', [
                'bill_id' => $document->id,
                'offering_id' => $lineItem->offering_id,
            ]);

            return;
        }

        // Record inventory movement (inbound) - this will auto-create batch if item tracks batches
        $inventoryService = app(InventoryService::class);
        $inventoryService->recordMovement(
            item: $inventoryItem,
            warehouse: $warehouse,
            quantity: $lineItem->quantity,
            movementType: MovementType::Purchase,
            unitCost: $lineItem->unit_price, // Already in cents (int)
            referenceType: get_class($document),
            referenceId: $document->id,
            movementDate: $document->date, // Use date, not issued_at
            notes: "Purchase via Bill #{$document->bill_number}"
        );

        Log::info('Bill inventory recorded', [
            'bill_number' => $document->bill_number,
            'item' => $lineItem->offering->name,
            'quantity' => $lineItem->quantity,
            'unit_cost' => $lineItem->unit_price, // Already in cents (int)
        ]);

        // Create accounting transaction if this is the first line item processed for this bill
        if (! $document->transactions()->exists()) {
            $document->createInitialTransaction();
        }

        // After receiving stock, check for flagged invoices that can now be fulfilled
        $this->checkAndClearInvoiceFlags($inventoryItem, $warehouse, $document->company_id, $inventoryService);
    }

    /**
     * Check flagged invoices and clear flags if sufficient stock now exists
     */
    protected function checkAndClearInvoiceFlags(
        InventoryItem $inventoryItem,
        Warehouse $warehouse,
        int $companyId,
        InventoryService $inventoryService
    ): void {
        // Find all flagged invoices in this company
        $flaggedInvoices = \App\Models\Accounting\Invoice::where('inventory_flagged', true)
            ->where('company_id', $companyId)
            ->get();

        Log::info('Checking flagged invoices for clearing', [
            'item' => $inventoryItem->name,
            'warehouse' => $warehouse->name,
            'flagged_count' => $flaggedInvoices->count(),
            'available_stock' => $inventoryService->getStockQuantity($inventoryItem, $warehouse),
        ]);

        foreach ($flaggedInvoices as $invoice) {
            Log::info('Examining invoice', [
                'invoice_number' => $invoice->invoice_number,
                'line_items_count' => $invoice->lineItems->count(),
            ]);

            // Check each line item in the invoice
            foreach ($invoice->lineItems as $invLine) {
                if (! $invLine->offering || ! $invLine->offering->inventoryItem) {
                    continue;
                }

                Log::info('Checking line item', [
                    'offering' => $invLine->offering->name ?? 'N/A',
                    'item_id' => $invLine->offering->inventoryItem->id ?? 'N/A',
                    'looking_for' => $inventoryItem->id,
                    'quantity' => $invLine->quantity,
                ]);

                // Only check items that match the one we just restocked
                if ($invLine->offering->inventoryItem->id !== $inventoryItem->id) {
                    Log::info('Skipping - different item');

                    continue;
                }

                $availableStock = $inventoryService->getStockQuantity($inventoryItem, $warehouse);
                $hasSufficient = $inventoryService->hasSufficientStock($inventoryItem, $warehouse, $invLine->quantity);

                Log::info('Stock check', [
                    'required' => $invLine->quantity,
                    'available' => $availableStock,
                    'has_sufficient' => $hasSufficient,
                ]);

                // Check if we now have sufficient stock for this invoice line
                if ($hasSufficient) {
                    // Clear the invoice flag
                    $invoice->clearInventoryFlag();

                    Log::info('Invoice flag cleared', [
                        'invoice_number' => $invoice->invoice_number,
                        'item' => $inventoryItem->name,
                        'required_quantity' => $invLine->quantity,
                        'available_quantity' => $availableStock,
                    ]);

                    // Only clear one invoice per bill line item (simple, low-risk approach)
                    break 2;
                } else {
                    Log::info('Not clearing - insufficient stock');
                }
            }
        }
    }

    /**
     * Handle the DocumentLineItem "deleted" event.
     */
    public function deleted(DocumentLineItem $documentLineItem): void
    {
        $documentLineItem->adjustments()->detach();
    }

    /**
     * Handle the DocumentLineItem "restored" event.
     */
    public function restored(DocumentLineItem $documentLineItem): void
    {
        //
    }

    /**
     * Handle the DocumentLineItem "force deleted" event.
     */
    public function forceDeleted(DocumentLineItem $documentLineItem): void
    {
        //
    }
}
