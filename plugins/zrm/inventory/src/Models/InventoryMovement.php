<?php

namespace Zrm\Inventory\Models;

use Erpsaas\Accounts\Models\Accounting\Transaction;
use Erpsaas\Core\Casts\MoneyCast;
use Erpsaas\Core\Concerns\Blamable;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Zrm\Inventory\Enums\MovementType;

class InventoryMovement extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'inventory_item_id',
        'warehouse_id',
        'batch_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'transaction_id',
        'notes',
        'movement_date',
        'created_by',
    ];

    protected $casts = [
        'movement_type' => MovementType::class,
        'quantity' => 'integer',
        'unit_cost' => MoneyCast::class,
        'total_cost' => MoneyCast::class,
        'movement_date' => 'date',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isInbound(): bool
    {
        return $this->movement_type->isInbound();
    }

    public function isOutbound(): bool
    {
        return $this->movement_type->isOutbound();
    }
}
