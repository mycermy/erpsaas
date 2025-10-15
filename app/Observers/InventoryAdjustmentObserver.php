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

        if ($wasNotApproved && $isApproved && ! $adjustment->wasRecentlyCreated) {
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

            // Record adjustment movement
            $inventoryService->recordMovement(
                item: $inventoryItem,
                warehouse: $adjustment->warehouse,
                quantity: $adjustmentItem->quantity, // Can be positive or negative
                movementType: MovementType::Adjustment,
                unitCost: 0, // Adjustments typically don't change cost
                movementDate: $adjustment->adjustment_date,
                referenceType: InventoryAdjustment::class,
                referenceId: $adjustment->id,
                notes: $adjustment->reason ?? $adjustmentItem->notes ?? 'Inventory adjustment'
            );
        }
    }
}
