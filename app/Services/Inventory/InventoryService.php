<?php

namespace App\Services\Inventory;

use App\Enums\Inventory\MovementType;
use App\Enums\Inventory\TrackMethod;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\InventoryStockLevel;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\Auth;
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
        ?\DateTime $movementDate = null,
        ?int $createdBy = null,
        $adjustmentType = null,
        array $batchAllocations = []
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
            $movementDate,
            $createdBy,
            $adjustmentType,
            $batchAllocations
        ) {            // Idempotency: if this movement references a specific reference (e.g. an adjustment)
            // and we've already recorded a movement for this item/reference, return the existing one
            \Illuminate\Support\Facades\Log::info('recordMovement called', [
                'item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => $quantity,
                'movement_type' => is_object($movementType) ? $movementType->value : $movementType,
                'unit_cost' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId
            ]);

            if ($referenceType && $referenceId) {
                $existing = InventoryMovement::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                    ->where('company_id', $item->company_id)
                    ->where('inventory_item_id', $item->id)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('movement_type', is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $totalCost = abs($quantity) * $unitCost;
            $batchId = null;
            // Don't reset $batchAllocations here - it's passed as a parameter

            // Special handling for adjustments: treat by sign
            if ($movementType === MovementType::Adjustment) {
                // Inbound adjustment (found stock): create a new batch when tracking batches
                if ($quantity > 0) {
                    if ($item->track_batches) {
                        // Use provided unitCost if available, otherwise fallback to current average_cost
                        $appliedUnitCost = $unitCost > 0 ? $unitCost : ($item->stockLevels()->where('warehouse_id', $warehouse->id)->value('average_cost') ?? 0);

                        $batch = $this->createBatch($item, $warehouse, $quantity, $appliedUnitCost, $movementDate ?? now(), 'ADJ-' . $referenceId, null, null, null, $movementType);

                        $movement = InventoryMovement::create([
                            'company_id' => $item->company_id,
                            'inventory_item_id' => $item->id,
                            'warehouse_id' => $warehouse->id,
                            'batch_id' => $batch->id,
                            'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                            'quantity' => $quantity,
                            'unit_cost' => $appliedUnitCost,
                            'total_cost' => abs($quantity) * $appliedUnitCost,
                            'reference_type' => $referenceType,
                            'reference_id' => $referenceId,
                            'transaction_id' => $transactionId,
                            'notes' => $notes,
                            'movement_date' => $movementDate ?? now(),
                            'created_by' => $createdBy ?? Auth::id(),
                        ]);

                        // Update stock level
                        $this->updateStockLevel($item, $warehouse, $quantity, $appliedUnitCost);

                        return $movement;
                    }

                    // Non-batch tracked inbound adjustment: simple movement
                    $movement = InventoryMovement::create([
                        'company_id' => $item->company_id,
                        'inventory_item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'batch_id' => null,
                        'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'total_cost' => $totalCost,
                        'reference_type' => $referenceType,
                        'reference_id' => $referenceId,
                        'transaction_id' => $transactionId,
                        'notes' => $notes,
                        'movement_date' => $movementDate ?? now(),
                        'created_by' => $createdBy ?? Auth::id(),
                    ]);

                    $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

                    return $movement;
                }

                // Outbound adjustment (lost/damaged stock): consume batches if tracked
                if ($quantity < 0) {
                    // Skip this legacy handling if manual batch allocations are provided
                    if (!empty($batchAllocations)) {
                        // Will be handled later in the code - skip the legacy handling
                        // Don't return here - let it continue to the manual batch selection code below
                    } else {
                        $absQty = abs($quantity);

                        if ($item->track_batches) {
                            $cogsCalculation = $this->calculateCOGS($item, $warehouse, $absQty);

                            if (isset($cogsCalculation['batches']) && ! empty($cogsCalculation['batches'])) {
                                $batchAllocations = $cogsCalculation['batches'];
                                $totalCost = $cogsCalculation['total_cost'];

                                if (count($batchAllocations) > 1) {
                                    foreach ($batchAllocations as $allocation) {
                                        InventoryMovement::create([
                                            'company_id' => $item->company_id,
                                            'inventory_item_id' => $item->id,
                                            'warehouse_id' => $warehouse->id,
                                            'batch_id' => $allocation['batch_id'],
                                            'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                                            'quantity' => -$allocation['quantity'], // Negative for outbound
                                            'unit_cost' => $allocation['unit_cost'],
                                            'total_cost' => $allocation['total_cost'],
                                            'reference_type' => $referenceType,
                                            'reference_id' => $referenceId,
                                            'transaction_id' => $transactionId,
                                            'notes' => $notes,
                                            'movement_date' => $movementDate ?? now(),
                                            'created_by' => $createdBy ?? Auth::id(),
                                        ]);
                                    }

                                    // Update stock level once with total
                                    $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

                                    // Reduce batch quantities
                                    $this->reduceBatches($batchAllocations);

                                    return InventoryMovement::where('inventory_item_id', $item->id)
                                        ->where('reference_type', $referenceType)
                                        ->where('reference_id', $referenceId)
                                        ->where('movement_type', is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType)
                                        ->latest()
                                        ->first();
                                }

                                // Single batch: create single movement below using $batchAllocations[0]
                                $batchId = $batchAllocations[0]['batch_id'] ?? null;
                                $unitCost = $batchAllocations[0]['unit_cost'] ?? $unitCost;
                                $totalCost = $batchAllocations[0]['total_cost'] ?? $totalCost;
                            }
                        }

                        // Fall back to standard single movement for outbound (no batches)
                        $movement = InventoryMovement::create([
                            'company_id' => $item->company_id,
                            'inventory_item_id' => $item->id,
                            'warehouse_id' => $warehouse->id,
                            'batch_id' => $batchId ?? null,
                            'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                            'quantity' => $quantity,
                            'unit_cost' => $unitCost,
                            'total_cost' => $totalCost,
                            'reference_type' => $referenceType,
                            'reference_id' => $referenceId,
                            'transaction_id' => $transactionId,
                            'notes' => $notes,
                            'movement_date' => $movementDate ?? now(),
                            'created_by' => $createdBy ?? Auth::id(),
                        ]);

                        // Update stock level
                        $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

                        // Reduce batches if single allocation
                        if (! empty($batchAllocations) && count($batchAllocations) === 1) {
                            $this->reduceBatches($batchAllocations);
                        }

                        return $movement;
                    }
                }

                // quantity == 0 -> noop
                if ($quantity == 0) {
                    return InventoryMovement::create([
                        'company_id' => $item->company_id,
                        'inventory_item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'batch_id' => null,
                        'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                        'quantity' => 0,
                        'unit_cost' => 0,
                        'total_cost' => 0,
                        'reference_type' => $referenceType,
                        'reference_id' => $referenceId,
                        'transaction_id' => $transactionId,
                        'notes' => $notes,
                        'movement_date' => $movementDate ?? now(),
                        'created_by' => $createdBy ?? Auth::id(),
                    ]);
                }
            }

            // Handle batch tracking for outbound movements or negative adjustments
            if (($movementType->isOutbound() || ($movementType === MovementType::Adjustment && $quantity < 0)) && $item->track_batches) {
                // Special handling for adjustments with manual batch selection
                if ($movementType === MovementType::Adjustment && $quantity < 0 && !empty($batchAllocations)) {
                    // Manual batch selection for damage adjustments
                    $totalCost = 0;
                    foreach ($batchAllocations as $allocation) {
                        $totalCost += $allocation['total_cost'];
                    }

                    // Create movement records for each batch allocation
                    $firstMovement = null;
                    foreach ($batchAllocations as $allocation) {
                        $movement = InventoryMovement::create([
                            'company_id' => $item->company_id,
                            'inventory_item_id' => $item->id,
                            'warehouse_id' => $warehouse->id,
                            'batch_id' => $allocation['batch_id'],
                            'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                            'quantity' => -$allocation['quantity'], // Negative for outbound
                            'unit_cost' => $allocation['unit_cost'],
                            'total_cost' => $allocation['total_cost'],
                            'reference_type' => $referenceType,
                            'reference_id' => $referenceId,
                            'transaction_id' => $transactionId,
                            'notes' => $notes,
                            'movement_date' => $movementDate ?? now(),
                            'created_by' => $createdBy ?? Auth::id(),
                        ]);

                        if (!$firstMovement) {
                            $firstMovement = $movement;
                        }
                    }

                    // Update stock level once with total
                    $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

                    // Reduce batch quantities
                    $this->reduceBatches($batchAllocations);

                    return $firstMovement;
                }

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
                                'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                                'quantity' => -$allocation['quantity'], // Negative for outbound
                                'unit_cost' => $allocation['unit_cost'],
                                'total_cost' => $allocation['total_cost'],
                                'reference_type' => $referenceType,
                                'reference_id' => $referenceId,
                                'transaction_id' => $transactionId,
                                'notes' => $notes,
                                'movement_date' => $movementDate ?? now(),
                                'created_by' => $createdBy ?? Auth::id(),
                            ]);
                        }

                        // Update stock level once with total
                        $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

                        // Reduce batch quantities
                        $this->reduceBatches($batchAllocations);

                        // Return the first movement as the primary record
                        return InventoryMovement::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                            ->where('inventory_item_id', $item->id)
                            ->where('reference_type', $referenceType)
                            ->where('reference_id', $referenceId)
                            ->where('movement_type', is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType)
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
                'movement_type' => is_object($movementType) && property_exists($movementType, 'value') ? $movementType->value : (string) $movementType,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'transaction_id' => $transactionId,
                'notes' => $notes,
                'movement_date' => $movementDate ?? now(),
                'created_by' => $createdBy ?? Auth::id(),
            ]);

            // Update stock level
            $this->updateStockLevel($item, $warehouse, $quantity, $unitCost);

            // Handle batch tracking for inbound movements or positive adjustments
            if (($movementType->isInbound() || ($movementType === MovementType::Adjustment && $quantity > 0)) && $item->track_batches) {
                $batchNumber = null;
                $billId = null;

                // If this is a purchase bill, link the batch to the bill
                if ($referenceType === 'App\\Models\\Accounting\\Bill') {
                    $billId = $referenceId;
                }

                $batch = $this->createBatch($item, $warehouse, $quantity, $unitCost, $movementDate ?? now(), $batchNumber, $billId, null, null, $movementType);

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
        // Query for existing stock level
        $stockLevel = InventoryStockLevel::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('company_id', $item->company_id)
            ->where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if (! $stockLevel) {
            try {
                $stockLevel = InventoryStockLevel::create([
                    'company_id' => $item->company_id,
                    'inventory_item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'quantity_available' => 0,
                    'average_cost' => 0,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Duplicate insertion in concurrent/test scenario; re-query
                $stockLevel = InventoryStockLevel::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
                    ->where('company_id', $item->company_id)
                    ->where('inventory_item_id', $item->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->first();
            }
        }

        // Update quantity - ensure $stockLevel is not null
        if (! $stockLevel) {
            // Create an in-memory model with defaults
            $stockLevel = new InventoryStockLevel([
                'company_id' => $item->company_id,
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_available' => 0,
                'average_cost' => 0,
            ]);
        }

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

        try {
            $stockLevel->save();
        } catch (\Exception $e) {
            throw $e;
        }
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
        ?\DateTime $expiryDate = null,
        ?MovementType $movementType = null
    ): InventoryBatch {
        return InventoryBatch::create([
            'company_id' => $item->company_id,
            'inventory_item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'batch_number' => $batchNumber ?? $this->generateBatchNumber($item, $warehouse),
            'lot_number' => $lotNumber ?? $this->generateLotNumber($item, $warehouse, $movementType),
            'quantity_received' => $quantity,
            'quantity_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => $receivedDate,
            'expiry_date' => $expiryDate,
            'bill_id' => $billId,
            'created_by' => $createdBy ?? Auth::id(),
        ]);
    }

    /**
     * Reduce batch quantities based on COGS calculation
     */
    public function reduceBatches(array $batchAllocations): void
    {
        foreach ($batchAllocations as $allocation) {
            $batch = InventoryBatch::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)->find($allocation['batch_id']);
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
        // Use year-based sequential numbering: 2024-001, 2024-002, etc.
        $year = now()->format('Y');

        // Get the next sequential number for this year
        $lastBatch = InventoryBatch::where('company_id', $item->company_id)
            ->where('batch_number', 'like', "{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastBatch) {
            // Extract the sequential number and increment
            $parts = explode('-', $lastBatch->batch_number);
            $sequential = (int) $parts[1] + 1;
        } else {
            $sequential = 1;
        }

        return sprintf('%s-%03d', $year, $sequential);
    }

    /**
     * Generate a unique lot number
     */
    protected function generateLotNumber(InventoryItem $item, Warehouse $warehouse, ?MovementType $movementType = null): string
    {
        $prefix = strtoupper(substr($warehouse->code ?? 'WH', 0, 3));
        $itemCode = strtoupper(substr($item->sku ?? $item->id, 0, 4));
        $yearMonth = now()->format('Ym'); // 202601 for January 2026

        // Determine activity prefix based on movement type
        $activityPrefix = $this->getActivityPrefix($movementType);

        // Get the next sequential number for this activity-warehouse-item-yearmonth combination
        $pattern = "{$activityPrefix}-{$prefix}-{$itemCode}-{$yearMonth}-%";
        $lastLot = InventoryBatch::where('company_id', $item->company_id)
            ->whereNotNull('lot_number')
            ->where('lot_number', 'like', $pattern)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastLot) {
            // Extract the sequential number and increment
            $parts = explode('-', $lastLot->lot_number);
            $sequential = (int) $parts[4] + 1;
        } else {
            $sequential = 1;
        }

        return sprintf('%s-%s-%s-%s-%03d', $activityPrefix, $prefix, $itemCode, $yearMonth, $sequential);
    }

    /**
     * Get activity prefix based on movement type
     */
    protected function getActivityPrefix(?MovementType $movementType): string
    {
        if (!$movementType) {
            return 'UNK'; // Unknown activity
        }

        return match ($movementType) {
            MovementType::Purchase => 'PUR',    // Purchase stock
            MovementType::Sale => 'SLS',        // Sales stock
            MovementType::Adjustment => 'DMG',  // Damage/write-off adjustments
            MovementType::TransferIn => 'TRI',  // Transfer in
            MovementType::TransferOut => 'TRO', // Transfer out
            MovementType::Return => 'RTN',      // Returns
            MovementType::Initial => 'INIT',    // Initial stock setup
            default => 'UNK'                    // Unknown activity
        };
    }

    /**
     * Check if item has sufficient stock
     */
    public function hasSufficientStock(
        InventoryItem $item,
        Warehouse $warehouse,
        float $quantity
    ): bool {
        $stockLevel = InventoryStockLevel::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('company_id', $item->company_id)
            ->where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        return $stockLevel && $stockLevel->quantity_available >= $quantity;
    }

    /**
     * Get current stock quantity for an item in a warehouse
     */
    public function getStockQuantity(InventoryItem $item, Warehouse $warehouse): float
    {
        $stockLevel = InventoryStockLevel::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('company_id', $item->company_id)
            ->where('inventory_item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        return $stockLevel ? $stockLevel->quantity_on_hand : 0;
    }
}
