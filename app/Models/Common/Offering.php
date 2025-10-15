<?php

namespace App\Models\Common;

use App\Concerns\Blamable;
use App\Concerns\CompanyOwned;
use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Common\OfferingType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Adjustment;
use App\Models\Inventory\InventoryItem;
use App\Observers\OfferingObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;

#[ObservedBy(OfferingObserver::class)]
class Offering extends Model
{
    use Blamable;
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'type',
        'price',
        'sellable',
        'purchasable',
        'stockable',
        'income_account_id',
        'expense_account_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => OfferingType::class,
        'sellable' => 'boolean',
        'purchasable' => 'boolean',
        'stockable' => 'boolean',
    ];

    public function clearSellableAdjustments(): void
    {
        if (! $this->sellable) {
            $this->income_account_id = null;

            $adjustmentIds = $this->salesAdjustments()->pluck('adjustment_id');

            $this->adjustments()->detach($adjustmentIds);
        }
    }

    public function clearPurchasableAdjustments(): void
    {
        if (! $this->purchasable) {
            $this->expense_account_id = null;

            $adjustmentIds = $this->purchaseAdjustments()->pluck('adjustment_id');

            $this->adjustments()->detach($adjustmentIds);
        }
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }

    public function adjustments(): MorphToMany
    {
        return $this->morphToMany(Adjustment::class, 'adjustmentable', 'adjustmentables');
    }

    public function salesAdjustments(): MorphToMany
    {
        return $this->adjustments()->where('type', AdjustmentType::Sales);
    }

    public function purchaseAdjustments(): MorphToMany
    {
        return $this->adjustments()->where('type', AdjustmentType::Purchase);
    }

    public function salesTaxes(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Tax)->where('type', AdjustmentType::Sales);
    }

    public function purchaseTaxes(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Tax)->where('type', AdjustmentType::Purchase);
    }

    public function salesDiscounts(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Discount)->where('type', AdjustmentType::Sales);
    }

    public function purchaseDiscounts(): MorphToMany
    {
        return $this->adjustments()->where('category', AdjustmentCategory::Discount)->where('type', AdjustmentType::Purchase);
    }

    public function hasInactiveAdjustments(): bool
    {
        return $this->adjustments->contains(function (Adjustment $adjustment) {
            return $adjustment->isInactive();
        });
    }

    public function hasInventoryTracking(): bool
    {
        return $this->inventoryItem()->exists();
    }

    public function isInventoryEnabled(): bool
    {
        return $this->inventoryItem && $this->inventoryItem->active;
    }

    public function isStockable(): bool
    {
        return $this->stockable && $this->type === OfferingType::Product;
    }

    public function ensureInventoryItem(): InventoryItem
    {
        if ($this->inventoryItem) {
            return $this->inventoryItem;
        }

        return $this->inventoryItem()->create([
            'company_id' => $this->company_id,
            'sku' => $this->generateSku(),
            'track_method' => 'fifo',
            'track_batches' => true,
            'reorder_level' => 0,
            'reorder_quantity' => 0,
            'active' => true,
            'created_by' => $this->created_by ?? Auth::id(),
        ]);
    }

    private function generateSku(): string
    {
        $prefix = strtoupper(substr($this->name, 0, 3));
        $random = strtoupper(substr(md5(uniqid()), 0, 6));

        return "{$prefix}-{$random}";
    }
}
