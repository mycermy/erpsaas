<?php

namespace App\Observers;

use App\Enums\Inventory\AdjustmentStatus;
use App\Enums\Inventory\MovementType;
use App\Models\Inventory\InventoryAdjustment;
use App\Services\Inventory\InventoryService;

class InventoryAdjustmentObserver
{
    /**
     * Handle the InventoryAdjustment "saving" event.
     * Process inventory movements when adjustment is approved.
     */
    public function saving(InventoryAdjustment $adjustment): void
    {
        // Only process when status changes to Approved
        $wasNotApproved = $adjustment->getOriginal('status') !== AdjustmentStatus::Approved;
        $isApproved = $adjustment->status === AdjustmentStatus::Approved;

        if ($wasNotApproved && $isApproved) {
            $this->processInventoryAdjustment($adjustment);
        }
    }

    /**
     * Process inventory movements for approved adjustment
     */
    protected function processInventoryAdjustment(InventoryAdjustment $adjustment): void
    {
        $inventoryService = app(InventoryService::class);

        foreach ($adjustment->items as $adjustmentItem) {
            $inventoryItem = $adjustmentItem->inventoryItem;

            if (! $inventoryItem) {
                continue;
            }

            // Determine movement type based on adjustment reason
            $movementType = $this->getMovementTypeForAdjustment($adjustment);

            // Record adjustment movement
            $inventoryService->recordMovement(
                item: $inventoryItem,
                warehouse: $adjustment->warehouse,
                quantity: $adjustmentItem->quantity_adjusted, // Can be positive or negative
                movementType: $movementType,
                unitCost: $adjustmentItem->unit_cost ?? 0, // Use the specified unit cost for adjustments
                movementDate: $adjustment->adjustment_date,
                referenceType: InventoryAdjustment::class,
                referenceId: $adjustment->id,
                notes: $adjustment->reason ?? $adjustmentItem->notes ?? 'Inventory adjustment'
            );
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
}
