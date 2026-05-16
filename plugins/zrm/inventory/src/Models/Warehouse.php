<?php

namespace Zrm\Inventory\Models;

use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'contact_name',
        'contact_phone',
        'contact_email',
        'is_default',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public function stockLevels(): HasMany
    {
        return $this->hasMany(InventoryStockLevel::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class);
    }

    public function transfersFrom(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'from_warehouse_id');
    }

    public function transfersTo(): HasMany
    {
        return $this->hasMany(InventoryTransfer::class, 'to_warehouse_id');
    }

    public function getTotalItemsAttribute(): int
    {
        return $this->stockLevels()->count();
    }

    public function getTotalValueAttribute(): int
    {
        return $this->stockLevels()->sum('average_cost');
    }
}
