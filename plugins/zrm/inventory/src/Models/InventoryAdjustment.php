<?php

namespace Zrm\Inventory\Models;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use Zrm\Inventory\Enums\AdjustmentStatus;
use Zrm\Inventory\Enums\AdjustmentType;
use Zrm\Inventory\Observers\InventoryAdjustmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(InventoryAdjustmentObserver::class)]
class InventoryAdjustment extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'adjustment_number',
        'adjustment_date',
        'adjustment_type',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'adjustment_type' => AdjustmentType::class,
        'status' => AdjustmentStatus::class,
        'approved_at' => 'datetime',
    ];

    /**
     * Backwards-compatibility accessor for `reference_number`.
     */
    public function getReferenceNumberAttribute(): ?string
    {
        return $this->attributes['adjustment_number'] ?? null;
    }

    /**
     * Backwards-compatibility mutator for `reference_number`.
     */
    public function setReferenceNumberAttribute(?string $value): void
    {
        $this->attributes['adjustment_number'] = $value;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class, 'adjustment_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function approve(int $userId): void
    {
        $this->status = AdjustmentStatus::Approved;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        // If approved, reverse the inventory movements first
        if ($this->isApproved()) {
            $this->reverseInventoryMovements();
        }

        $this->status = AdjustmentStatus::Cancelled;
        $this->save();
    }

    /**
     * Reverse inventory movements for a cancelled adjustment (BATCH-AWARE)
     * 
     * This method reverses inventory movements by:
     * 1. Loading all original movements created by this adjustment
     * 2. Using InventoryService to properly reverse with batch traceability
     * 3. For inbound adjustments: reverses batch creation
     * 4. For outbound adjustments: restores exact batches consumed
     */
    protected function reverseInventoryMovements(): void
    {
        \Illuminate\Support\Facades\Log::info('Reversing inventory movements (batch-aware)', [
            'adjustment_id' => $this->id,
            'adjustment_number' => $this->adjustment_number,
        ]);

        // Load relationships without global scope
        $this->load([
            'warehouse' => function ($query) {
                $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
            },
            'items' => function ($query) {
                $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
            },
            'items.inventoryItem' => function ($query) {
                $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
            },
            'items.batchAllocations' => function ($query) {
                $query->withoutGlobalScope(\App\Scopes\CurrentCompanyScope::class);
            },
        ]);

        $inventoryService = app(\Zrm\Inventory\Services\InventoryService::class);

        // Group movements by adjustment item to handle batch allocations properly
        foreach ($this->items as $adjustmentItem) {
            if (!$adjustmentItem->inventoryItem) {
                \Illuminate\Support\Facades\Log::warning('Skipping item without inventory item', [
                    'adjustment_item_id' => $adjustmentItem->id,
                ]);
                continue;
            }

            $quantity = $adjustmentItem->quantity_adjusted ?? 0;

            // Skip zero adjustments
            if ((int) $quantity === 0) {
                continue;
            }

            \Illuminate\Support\Facades\Log::info('Reversing adjustment item', [
                'adjustment_id' => $this->id,
                'item_id' => $adjustmentItem->id,
                'inventory_item_id' => $adjustmentItem->inventoryItem->id,
                'original_quantity' => $quantity,
                'reverse_quantity' => -$quantity,
            ]);

            try {
                // For outbound adjustments (negative quantity) with batch allocations,
                // we need to restore the SAME batches that were consumed
                $batchAllocations = [];
                if ($quantity < 0 && $adjustmentItem->batchAllocations->isNotEmpty()) {
                    // Build batch allocations array to restore the exact batches
                    $batchAllocations = $adjustmentItem->batchAllocations->map(function ($batchAllocation) {
                        return [
                            'batch_id' => $batchAllocation->inventory_batch_id,
                            'quantity' => $batchAllocation->quantity,
                            'unit_cost' => $batchAllocation->unit_cost,
                            'total_cost' => $batchAllocation->total_cost,
                        ];
                    })->toArray();

                    \Illuminate\Support\Facades\Log::info('Using batch allocations for reversal', [
                        'adjustment_item_id' => $adjustmentItem->id,
                        'batch_allocations' => $batchAllocations,
                    ]);
                }

                // Use InventoryService to record the reverse movement
                // This ensures proper stock level updates and batch handling
                $inventoryService->recordMovement(
                    item: $adjustmentItem->inventoryItem,
                    warehouse: $this->warehouse,
                    quantity: -$quantity, // Reverse sign
                    movementType: \Zrm\Inventory\Enums\MovementType::Adjustment,
                    unitCost: $adjustmentItem->unit_cost ?? 0,
                    referenceType: static::class,
                    referenceId: $this->id,
                    notes: "REVERSAL: {$adjustmentItem->reason} (Adjustment #{$this->adjustment_number} cancelled)",
                    movementDate: now(),
                    createdBy: \Illuminate\Support\Facades\Auth::id(),
                    batchAllocations: $batchAllocations
                );

                \Illuminate\Support\Facades\Log::info('Successfully reversed adjustment item', [
                    'adjustment_item_id' => $adjustmentItem->id,
                ]);

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to reverse adjustment item', [
                    'adjustment_id' => $this->id,
                    'item_id' => $adjustmentItem->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e; // Re-throw to ensure transaction rollback
            }
        }

        \Illuminate\Support\Facades\Log::info('Inventory movements reversed successfully', [
            'adjustment_id' => $this->id,
            'items_reversed' => $this->items->count(),
        ]);
    }

    public function isDraft(): bool
    {
        return $this->status === AdjustmentStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === AdjustmentStatus::Approved;
    }

    public function isCancelled(): bool
    {
        return $this->status === AdjustmentStatus::Cancelled;
    }

    protected static function booted(): void
    {
        // Ensure observer is registered in all environments (some test harnesses may not pick up attributes)
        static::observe(InventoryAdjustmentObserver::class);
    }
}
