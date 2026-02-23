<?php

namespace App\Observers;

use App\Enums\Common\OfferingType;
use App\Models\Common\Offering;
use Zrm\Inventory\Enums\TrackMethod;
use Zrm\Inventory\Models\InventoryItem;

class OfferingObserver
{
    /**
     * Handle the Offering "created" event.
     */
    public function created(Offering $offering): void
    {
        // Automatically create inventory item for products
        if ($offering->type === OfferingType::Product) {
            $this->createInventoryItem($offering);
        }
    }

    public function saving(Offering $offering): void
    {
        $offering->clearSellableAdjustments();
        $offering->clearPurchasableAdjustments();

        // Auto-create inventory item when stockable is enabled
        if ($offering->stockable && $offering->type === OfferingType::Product) {
            // This will be handled after save in the 'saved' event
        }
    }

    public function saved(Offering $offering): void
    {
        // Auto-create inventory item when stockable is enabled
        if ($offering->stockable && $offering->type === OfferingType::Product && ! $offering->inventoryItem) {
            $offering->ensureInventoryItem();
        }

        // Deactivate inventory item when stockable is disabled
        if (! $offering->stockable && $offering->inventoryItem) {
            $offering->inventoryItem->update(['active' => false]);
        }
    }

    /**
     * Handle the Offering "updated" event.
     */
    public function updated(Offering $offering): void
    {
        // If type changed to Product, create inventory item
        if ($offering->wasChanged('type') && $offering->type === OfferingType::Product) {
            if (! $offering->inventoryItem) {
                $this->createInventoryItem($offering);
            }
        }

        // If type changed from Product to Service, you might want to deactivate inventory item
        if ($offering->wasChanged('type') && $offering->getOriginal('type') === OfferingType::Product->value) {
            if ($offering->inventoryItem) {
                $offering->inventoryItem->update(['active' => false]);
            }
        }
    }

    /**
     * Handle the Offering "deleted" event.
     */
    public function deleted(Offering $offering): void
    {
        // Deactivate inventory item when offering is deleted
        if ($offering->inventoryItem) {
            $offering->inventoryItem->update(['active' => false]);
        }
    }

    /**
     * Handle the Offering "restored" event.
     */
    public function restored(Offering $offering): void
    {
        // Reactivate inventory item when offering is restored
        if ($offering->inventoryItem) {
            $offering->inventoryItem->update(['active' => true]);
        }
    }

    /**
     * Handle the Offering "force deleted" event.
     */
    public function forceDeleted(Offering $offering): void
    {
        //
    }

    /**
     * Create an inventory item for the offering
     */
    private function createInventoryItem(Offering $offering): void
    {
        // Generate SKU if not provided
        $sku = $this->generateSku($offering);

        InventoryItem::create([
            'company_id' => $offering->company_id,
            'offering_id' => $offering->id,
            'sku' => $sku,
            'track_method' => TrackMethod::FIFO, // Default to FIFO
            'reorder_level' => 10, // Default reorder level
            'reorder_quantity' => 25, // Default reorder quantity
            'track_batches' => true, // Default to track batches
            'active' => true,
            'created_by' => $offering->created_by,
        ]);
    }

    /**
     * Generate a SKU for the offering
     */
    private function generateSku(Offering $offering): string
    {
        // Create SKU from offering name
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $offering->name), 0, 8));
        $suffix = str_pad((string) $offering->id, 4, '0', STR_PAD_LEFT);

        $sku = $prefix . '-' . $suffix;

        // Check if SKU already exists, if so add random suffix
        $counter = 1;
        while (InventoryItem::where('company_id', $offering->company_id)
            ->where('sku', $sku)
            ->exists()) {
            $sku = $prefix . '-' . $suffix . '-' . $counter;
            $counter++;
        }

        return $sku;
    }
}
