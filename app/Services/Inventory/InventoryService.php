<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\TrackMethod;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStockLevel;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record a stock movement (purchase, sale, adjustment, etc.)
     */
    public function recordMovement(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity,
        MovementType $movementType,
        int $unitCost = 0,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $transactionId = null,
        ?string $notes = null,
        ?\DateTime $movementDate = null
    ): InventoryMovement {
        return DB::transaction(function () use (
            $item,
            $warehouse,
            $quantity,
            $movementType,
            $unitCost,
            $referenceType,
            $referenceId,
            $transactionId,
            $notes,
            $movementDate
        ) {
            $totalCost = abs($quantity) * $unitCost;
            $batchId = null;
            $batchAllocations = [];

            // Handle batch tracking for outbound movements (Sales, Adjustments, Transfers)
            if ($movementType->isOutbound() && $item->track_batches) {
                // Calculate COGS and get batch allocations
                $cogsCalculation = $this->calculateCOGS($item, $warehouse, abs($quantity));

                if (isset($cogsCalculation['batches']) && ! empty($cogsCalculation['batches'])) {
                    $batchAllocations = $cogsCalculation['batches'];

                    // Update total cost from COGS calculation
                    $totalCost = $cogsCalculation['total_cost'];

                    // If unit cost wasn't provided, calculate weighted average from COGS
                    if ($unitCost === 0 && abs($quantity) > 0) {
                        $unitCost = (int) round($totalCost / abs($quantity));
                    }

                    // Create SEPARATE movement records for EACH batch consumed
                    if (count($batchAllocations) > 1) {
                        // Multiple batches: create one movement per batch for full traceability
                        foreach ($batchAllocations as $allocation) {
                            InventoryMovement::create([
                                'company_id' => $item->company_id,
                                'inventory_item_id' => $item->id,
                                'warehouse_id' => $warehouse->id,
                                'batch_id' => $allocation['batch_id'],
                                'movement_type' => $movementType,
                                'quantity' => -$allocation['quantity'], // Negative for outbound
                                'unit_cost' => $allocation['unit_cost'],
                                'total_cost' => $allocation['total_cost'],
                                'reference_type' => $referenceType,
                                'reference_id' => $referenceId,
                                'transaction_id' => $transactionId,
                                'notes' => $notes,
                                'movement_date' => $movementDate ?? now(),
                            ]);
                        }

                        // Update stock level once with total
                        $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

                        // Reduce batch quantities
                        $this->reduceBatches($batchAllocations);

                        // Return the first movement as the primary record
                        return InventoryMovement::where('inventory_item_id', $item->id)
                            ->where('reference_type', $referenceType)
                            ->where('reference_id', $referenceId)
                            ->where('movement_type', $movementType)
                            ->latest()
                            ->first();
                    }

                    // Single batch: use standard single movement record
                    $batchId = $batchAllocations[0]['batch_id'] ?? null;
                }
            }

            // Create movement record (for inbound, non-tracked, or single-batch outbound)
            $movement = InventoryMovement::create([
                'company_id' => $item->company_id,
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'batch_id' => $batchId ?? null,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'transaction_id' => $transactionId,
                'notes' => $notes,
                'movement_date' => $movementDate ?? now(),
            ]);

            // Update stock level
            $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

            // Handle batch tracking for inbound movements
            if ($movementType->isInbound() && $item->track_batches) {
                $batch = $this->createBatch($item, $warehouse, $quantity, $unitCost, $movementDate ?? now(), $referenceId);

                // Link the movement to the created batch
                $movement->update(['batch_id' => $batch->id]);
            }

            // Reduce batch quantities for single-batch outbound movements
            if (! empty($batchAllocations) && count($batchAllocations) === 1) {
                $this->reduceBatches($batchAllocations);
            }

            return $movement;
        });
    }

    /**
     * Calculate COGS for a sale using FIFO/LIFO/Average method
     */
    public function calculateCOGS(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity
    ): array {
        return match ($item->track_method) {
            TrackMethod::FIFO => $this->calculateFIFO($item, $warehouse, $quantity),
            TrackMethod::LIFO => $this->calculateLIFO($item, $warehouse, $quantity),
            TrackMethod::Average => $this->calculateAverage($item, $warehouse, $quantity),
        };
    }

    /**
     * FIFO: First In, First Out
     */
    protected function calculateFIFO(InventoryItem $item, Warehouse $warehouse, float $quantity): array
    {
        $batches = InventoryBatch::where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_date')
            ->orderBy('id')
            ->get();

        return $this->allocateBatches($batches, $quantity);
    }

    /**
     * LIFO: Last In, First Out
     */
    protected function calculateLIFO(InventoryItem $item, Warehouse $warehouse, float $quantity): array
    {
        $batches = InventoryBatch::where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity_remaining', '>', 0)
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->get();

        return $this->allocateBatches($batches, $quantity);
    }

    /**
     * Weighted Average Cost
     */
    protected function calculateAverage(InventoryItem $item, Warehouse $warehouse, float $quantity): array
    {
        $stockLevel = InventoryStockLevel::where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if (! $stockLevel || $stockLevel->quantity_on_hand <= 0) {
            return [
                'total_cost' => 0,
                'batches' => [],
            ];
        }

        $averageCost = $stockLevel->average_cost;
        $totalCost = (int) round($quantity * $averageCost);

        // Even with average cost, we need to reduce batch quantities (use FIFO for batch reduction)
        $batches = InventoryBatch::where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_date')
            ->orderBy('id')
            ->get();

        $batchAllocations = $this->allocateBatches($batches, $quantity);

        return [
            'total_cost' => $totalCost,
            'average_cost' => $averageCost,
            'batches' => $batchAllocations['batches'], // Include batch allocations for quantity tracking
        ];
    }

    /**
     * Allocate quantity from batches
     */
    protected function allocateBatches($batches, float $quantity): array
    {
        $remainingQty = $quantity;
        $totalCost = 0;
        $allocatedBatches = [];

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) {
                break;
            }

            $qtyFromBatch = min($remainingQty, $batch->quantity_remaining);
            $costFromBatch = (int) round($qtyFromBatch * $batch->unit_cost);

            $allocatedBatches[] = [
                'batch_id' => $batch->id,
                'quantity' => $qtyFromBatch,
                'unit_cost' => $batch->unit_cost,
                'total_cost' => $costFromBatch,
            ];

            $totalCost += $costFromBatch;
            $remainingQty -= $qtyFromBatch;
        }

        return [
            'total_cost' => $totalCost,
            'batches' => $allocatedBatches,
        ];
    }

    /**
     * Update stock level for warehouse
     */
    protected function updateStockLevel(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity,
        int $unitCost
    ): void {
        $stockLevel = InventoryStockLevel::firstOrCreate(
            [
                'company_id' => $item->company_id,
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
                'average_cost' => 0,
            ]
        );

        // Update quantity
        $oldQuantity = $stockLevel->quantity_on_hand;
        $newQuantity = $oldQuantity + $quantity;

        // Calculate new weighted average cost (for inbound movements)
        if ($quantity > 0 && $unitCost > 0) {
            $oldTotalCost = $oldQuantity * $stockLevel->average_cost;
            $newTotalCost = $quantity * $unitCost;
            $stockLevel->average_cost = $newQuantity > 0
                ? (int) round(($oldTotalCost + $newTotalCost) / $newQuantity)
                : 0;
        }

        $stockLevel->quantity_on_hand = $newQuantity;
        $stockLevel->quantity_available = $newQuantity - $stockLevel->quantity_reserved;
        $stockLevel->last_movement_at = now();
        $stockLevel->save();
    }

    /**
     * Create a new batch for inbound inventory
     */
    public function createBatch(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity,
        int $unitCost,
        \DateTime $receivedDate,
        ?string $batchNumber = null,
        ?int $billId = null,
        ?string $lotNumber = null,
        ?\DateTime $expiryDate = null
    ): InventoryBatch {
        return InventoryBatch::create([
            'company_id' => $item->company_id,
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => $batchNumber ?? $this->generateBatchNumber($item, $warehouse),
            'lot_number' => $lotNumber,
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => $receivedDate,
            'expiry_date' => $expiryDate,
            'bill_id' => $billId,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Reduce batch quantities based on COGS calculation
     */
    public function reduceBatches(array $batchAllocations): void
    {
        foreach ($batchAllocations as $allocation) {
            $batch = InventoryBatch::find($allocation['batch_id']);
            if ($batch) {
                $batch->reduceQuantity($allocation['quantity']);
            }
        }
    }

    /**
     * Generate a unique batch number
     */
    protected function generateBatchNumber(InventoryItem $item, Warehouse $warehouse): string
    {
        $prefix = strtoupper(substr($warehouse->code ?? 'WH', 0, 3));
        $itemCode = strtoupper(substr($item->sku ?? $item->id, 0, 4));
        $timestamp = now()->format('ymdHis');

        return "{$prefix}-{$itemCode}-{$timestamp}";
    }

    /**
     * Check if item has sufficient stock
     */
    public function hasSufficientStock(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity
    ): bool {
        $stockLevel = InventoryStockLevel::where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        return $stockLevel && $stockLevel->quantity_available >= $quantity;
    }

    /**
     * Get current stock quantity for an item in a warehouse
     */
    public function getStockQuantity(InventoryItem $item, Warehouse $warehouse): float
    {
        $stockLevel = InventoryStockLevel::where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        return $stockLevel ? $stockLevel->quantity_on_hand : 0;
    }
}
