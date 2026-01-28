<?php

namespace Modules\Inventory\Models;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use Modules\Inventory\Enums\AdjustmentStatus;
use Modules\Inventory\Enums\AdjustmentType;
use Modules\Inventory\Observers\InventoryAdjustmentObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(InventoryAdjustmentObserver::class)]
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
        'adjustment_type',
        'status',
        'reason',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'adjustment_type' => AdjustmentType::class,
        'status' => AdjustmentStatus::class,
        'approved_at' => 'datetime',
    ];

    /**
     * Backwards-compatibility accessor for `reference_number`.
     */
    public function getReferenceNumberAttribute(): ?string
    {
        return $this->attributes['adjustment_number'] ?? null;
    }

    /**
     * Backwards-compatibility mutator for `reference_number`.
     */
    public function setReferenceNumberAttribute(?string $value): void
    {
        $this->attributes['adjustment_number'] = $value;
    }

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

    protected static function booted(): void
    {
        // Ensure observer is registered in all environments (some test harnesses may not pick up attributes)
        static::observe(InventoryAdjustmentObserver::class);
    }
}
