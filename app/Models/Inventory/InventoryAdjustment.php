<?php

namespace App\Models\Inventory;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Inventory\AdjustmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'adjustment_number',
        'adjustment_date',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'status' => AdjustmentStatus::class,
        'approved_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentItem::class, 'adjustment_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function approve(int $userId): void
    {
        $this->status = AdjustmentStatus::Approved;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->save();
    }

    public function cancel(): void
    {
        $this->status = AdjustmentStatus::Cancelled;
        $this->save();
    }

    public function isDraft(): bool
    {
        return $this->status === AdjustmentStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === AdjustmentStatus::Approved;
    }

    public function isCancelled(): bool
    {
        return $this->status === AdjustmentStatus::Cancelled;
    }
}
