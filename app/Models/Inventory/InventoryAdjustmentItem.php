<?php

namespace App\Models\Inventory;

use App\Casts\MoneyCast;
use App\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'quantity_adjusted' => 'decimal:2',
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

    public function isDecrease(): bool
    {
        return $this->quantity_adjusted < 0;
    }
}
