<?php

namespace Modules\Inventory\Models;

use App\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferItem extends Model
{
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'transfer_id',
        'inventory_item_id',
        'quantity_requested',
        'quantity_shipped',
        'quantity_received',
        'notes',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_shipped' => 'integer',
        'quantity_received' => 'integer',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'transfer_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function isFullyShipped(): bool
    {
        return $this->quantity_shipped >= $this->quantity_requested;
    }

    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity_shipped;
    }

    public function getVarianceAttribute(): float
    {
        return $this->quantity_received - $this->quantity_shipped;
    }
}
