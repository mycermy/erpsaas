<?php

namespace Zrm\Inventory\Models;

use Erpsaas\Core\Casts\MoneyCast;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustmentItem extends Model
{
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'adjustment_id',
        'inventory_item_id',
        'quantity_before',
        'quantity_after',
        'quantity_adjusted',
        'unit_cost',
        'reason',
    ];

    protected $casts = [
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'quantity_adjusted' => 'integer',
        'unit_cost' => MoneyCast::class,
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'adjustment_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function isIncrease(): bool
    {
        return $this->quantity_adjusted > 0;
    }

    public function batchAllocations(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentBatch::class, 'adjustment_item_id');
    }
}
