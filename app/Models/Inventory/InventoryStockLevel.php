<?php

namespace App\Models\Inventory;

use App\Casts\MoneyCast;
use App\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStockLevel extends Model
{
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'inventory_item_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'quantity_available',
        'average_cost',
        'last_movement_at',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'quantity_reserved' => 'decimal:2',
        'quantity_available' => 'decimal:2',
        'average_cost' => MoneyCast::class,
        'last_movement_at' => 'datetime',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function updateQuantity(float $change): void
    {
        $this->quantity_on_hand += $change;
        $this->quantity_available = $this->quantity_on_hand - $this->quantity_reserved;
        $this->last_movement_at = now();
        $this->save();
    }

    public function reserveQuantity(float $quantity): bool
    {
        if ($this->quantity_available < $quantity) {
            return false;
        }

        $this->quantity_reserved += $quantity;
        $this->quantity_available = $this->quantity_on_hand - $this->quantity_reserved;
        $this->save();

        return true;
    }

    public function releaseReservation(float $quantity): void
    {
        $this->quantity_reserved = max(0, $this->quantity_reserved - $quantity);
        $this->quantity_available = $this->quantity_on_hand - $this->quantity_reserved;
        $this->save();
    }
}
