<?php

namespace Zrm\Inventory\Models;

use App\Models\User;
use Erpsaas\Core\Concerns\CompanyOwned;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustmentImport extends Model
{
    use CompanyOwned;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'adjustment_id',
        'imported_by',
        'file_name',
        'status',
        'unknown_sku_strategy',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'error_summary',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'error_summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'adjustment_id');
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentImportRow::class, 'import_id');
    }
}
