<?php

namespace App\Observers;

use App\Enums\Accounting\DocumentType;
use App\Enums\Inventory\MovementType;
use App\Models\Accounting\DocumentLineItem;
use App\Services\Inventory\COGSService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Log;

class DocumentLineItemObserver
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected COGSService $cogsService
    ) {}

    /**
     * Handle the DocumentLineItem "created" event.
     */
    public function created(DocumentLineItem $documentLineItem): void
    {
        $this->processInventoryMovement($documentLineItem);
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
        // Only process if the document is approved/paid and item has inventory tracking
        if (! $this->shouldProcessInventory($lineItem)) {
            return;
        }

        $document = $lineItem->document;
        
        try {
            if ($document->type === DocumentType::Invoice) {
                $this->handleInvoiceLineItem($lineItem);
            } elseif ($document->type === DocumentType::Bill) {
                $this->handleBillLineItem($lineItem);
            }
        } catch (\Exception $e) {
            Log::error('Inventory integration error', [
                'document_type' => $document->type->value,
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
        // Check if offering has inventory tracking enabled
        if (! $lineItem->offering?->inventoryItem?->isInventoryEnabled()) {
            return false;
        }

        $document = $lineItem->document;
        
        // Only process when document is approved or paid
        return in_array($document->status->value, ['approved', 'paid', 'partial']);
    }

    protected function handleInvoiceLineItem(DocumentLineItem $lineItem): void
    {
        $document = $lineItem->document;
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
        $cogsCalculation = $this->inventoryService->calculateCOGS(
            $inventoryItem,
            $warehouse,
            $lineItem->quantity
        );

        // Record inventory movement (outbound)
        $movement = $this->inventoryService->recordMovement(
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
            $this->inventoryService->reduceBatches($cogsCalculation['batches']);
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
        $document = $lineItem->document;
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
        
        // Determine warehouse (use default or first available, or create initial stock level)
        $warehouse = $inventoryItem->warehouses()->first();
        
        if (! $warehouse) {
            // Get any warehouse or the default one
            $warehouse = \App\Models\Inventory\Warehouse::where('company_id', $document->company_id)
                ->where('active', true)
                ->first();
                
            if (! $warehouse) {
                Log::warning('No warehouse found for bill line item', [
                    'bill_id' => $document->id,
                    'offering_id' => $lineItem->offering_id,
                ]);
                return;
            }
        }

        // Create batch for this purchase
        $batch = $this->inventoryService->createBatch(
            item: $inventoryItem,
            warehouse: $warehouse,
            quantity: $lineItem->quantity,
            unitCost: $lineItem->unit_price->getAmount(),
            receivedDate: $document->issued_at,
            batchNumber: "BILL-{$document->document_number}",
            billId: $document->id
        );

        // Record inventory movement (inbound)
        $this->inventoryService->recordMovement(
            item: $inventoryItem,
            warehouse: $warehouse,
            quantity: $lineItem->quantity,
            movementType: MovementType::Purchase,
            unitCost: $lineItem->unit_price->getAmount(),
            referenceType: get_class($document),
            referenceId: $document->id,
            movementDate: $document->issued_at,
            notes: "Purchase via Bill #{$document->document_number}"
        );

        Log::info('Bill inventory recorded', [
            'bill_number' => $document->document_number,
            'item' => $lineItem->offering->name,
            'quantity' => $lineItem->quantity,
            'unit_cost' => $lineItem->unit_price->getAmount(),
        ]);
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
