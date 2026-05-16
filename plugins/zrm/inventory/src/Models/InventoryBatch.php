<?php

namespace Zrm\Inventory\Models;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Casts\MoneyCast;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBatch extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'inventory_item_id',
        'warehouse_id',
        'batch_number',
        'lot_number',
        'quantity_received',
        'quantity_remaining',
        'unit_cost',
        'received_date',
        'expiry_date',
        'bill_id',
        'created_by',
    ];

    protected $casts = [
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
        'unit_cost' => MoneyCast::class,
        'received_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'batch_id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isAvailable(): bool
    {
        return $this->quantity_remaining > 0 && ! $this->isExpired();
    }

    public function reduceQuantity(float $quantity): void
    {
        $this->quantity_remaining = max(0, $this->quantity_remaining - $quantity);
        $this->save();
    }
}
