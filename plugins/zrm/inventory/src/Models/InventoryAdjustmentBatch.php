<?php

namespace Zrm\Inventory\Models;

use Erpsaas\Core\Casts\MoneyCast;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentBatch extends Model
{
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'adjustment_item_id',
        'inventory_batch_id',
        'quantity',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => MoneyCast::class,
        'total_cost' => MoneyCast::class,
    ];

    public function adjustmentItem(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustmentItem::class, 'adjustment_item_id');
    }

    public function inventoryBatch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }
}
