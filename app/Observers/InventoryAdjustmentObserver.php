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
    public function saved(InventoryAdjustment $adjustment): void
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
        // Load items without global scope since scope may not work in observer context
        $items = \App\Models\Inventory\InventoryAdjustmentItem::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)
            ->where('adjustment_id', $adjustment->id)
            ->get();
        $adjustment->setRelation('items', $items);
        $adjustment->load(['warehouse' => function ($query) {
            $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
        }]);
        $inventoryService = app(InventoryService::class);
        foreach ($adjustment->items as $adjustmentItem) {
            $inventoryItem = \App\Models\Inventory\InventoryItem::withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class)->find($adjustmentItem->inventory_item_id);

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
            $inventoryService->recordMovement(
                item: $inventoryItem,
                warehouse: $adjustment->warehouse,
                quantity: $quantity, // positive = increase, negative = decrease
                movementType: $movementType,
                unitCost: $adjustmentItem->unit_cost ?? 0, // Use the specified unit cost for adjustments
                movementDate: $movementDate,
                referenceType: InventoryAdjustment::class,
                referenceId: $adjustment->id,
                notes: $notes,
                createdBy: $adjustment->created_by ?? null
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
