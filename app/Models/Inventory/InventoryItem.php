<?php

namespace App\Models\Inventory;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Inventory\TrackMethod;
use App\Models\Accounting\Account;
use App\Models\Common\Offering;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'offering_id',
        'sku',
        'track_method',
        'reorder_level',
        'reorder_quantity',
        'asset_account_id',
        'cogs_account_id',
        'track_batches',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'track_method' => TrackMethod::class,
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'track_batches' => 'boolean',
        'active' => 'boolean',
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getTotalQuantityAttribute(): float
    {
        return $this->stockLevels()->sum('quantity_on_hand');
    }

    public function getTotalAvailableAttribute(): float
    {
        return $this->stockLevels()->sum('quantity_available');
    }

    public function getStockLevelForWarehouse(int $warehouseId): ?InventoryStockLevel
    {
        return $this->stockLevels()
            ->where('warehouse_id', $warehouseId)
            ->first();
    }

    public function isLowStock(): bool
    {
        return $this->total_available <= $this->reorder_level;
    }

    /**
     * Return whether inventory tracking is enabled for this item.
     */
    public function isInventoryEnabled(): bool
    {
        return $this->active && (! $this->offering || $this->offering->sellable || $this->offering->purchasable || true);
    }
}
