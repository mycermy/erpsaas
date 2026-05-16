<?php

namespace Zrm\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentImportRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'row_number',
        'sku',
        'item_name',
        'quantity_before',
        'quantity_counted',
        'quantity_adjusted',
        'action',
        'status',
        'error_message',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustmentImport::class, 'import_id');
    }
}
