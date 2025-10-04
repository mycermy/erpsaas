<?php

namespace App\Models\Inventory;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Inventory\TransferStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransfer extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'transfer_date',
        'status',
        'notes',
        'shipped_at',
        'received_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'status' => TransferStatus::class,
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransferItem::class, 'transfer_id');
    }

    public function ship(): void
    {
        $this->status = TransferStatus::InTransit;
        $this->shipped_at = now();
        $this->save();
    }

    public function receive(): void
    {
        $this->status = TransferStatus::Received;
        $this->received_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = TransferStatus::Cancelled;
        $this->save();
    }

    public function isPending(): bool
    {
        return $this->status === TransferStatus::Pending;
    }

    public function isInTransit(): bool
    {
        return $this->status === TransferStatus::InTransit;
    }

    public function isReceived(): bool
    {
        return $this->status === TransferStatus::Received;
    }

    public function isCancelled(): bool
    {
        return $this->status === TransferStatus::Cancelled;
    }
}
