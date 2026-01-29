<?php

namespace Zrm\Inventory\Console\Commands;

use Zrm\Inventory\Enums\AdjustmentStatus;
use Zrm\Inventory\Enums\MovementType;
use Zrm\Inventory\Models\InventoryAdjustment;
use Zrm\Inventory\Services\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReprocessInventoryAdjustment extends Command
{
    protected $signature = 'inventory:reprocess-adjustment {id : InventoryAdjustment id} {--created-by= : user id to attribute to created records}';

    protected $description = 'Idempotently re-process an InventoryAdjustment and create missing inventory movements';

    public function handle(InventoryService $inventoryService): int
    {
        $id = $this->argument('id');
        $createdBy = $this->option('created-by') ? (int) $this->option('created-by') : null;

        $adjustment = InventoryAdjustment::with('items.inventoryItem', 'warehouse')->find($id);

        if (! $adjustment) {
            $this->error("InventoryAdjustment id={$id} not found.");

            return self::FAILURE;
        }

        if ($adjustment->status !== AdjustmentStatus::Approved) {
            $this->warn('Adjustment is not Approved. Processing will still run but movements will be recorded.');
        }

        DB::transaction(function () use ($adjustment, $inventoryService, $createdBy) {
            foreach ($adjustment->items as $item) {
                $inventoryItem = $item->inventoryItem;
                if (! $inventoryItem) {
                    $this->warn("Skipping adjustment item {$item->id}: missing inventory item");

                    continue;
                }

                $quantity = $item->quantity_adjusted ?? (($item->quantity_after ?? 0) - ($item->quantity_before ?? 0));
                if ((int) $quantity === 0) {
                    continue;
                }

                $notes = $item->reason ?? $adjustment->reason ?? 'Inventory adjustment (reprocessed)';

                $inventoryService->recordMovement(
                    $inventoryItem,
                    $adjustment->warehouse,
                    $quantity,
                    MovementType::Adjustment,
                    (int) ($item->unit_cost ?? 0),
                    InventoryAdjustment::class,
                    $adjustment->id,
                    null,
                    $notes,
                    $adjustment->adjustment_date,
                    $createdBy
                );
            }
        });

        $this->info("Processed adjustment id={$adjustment->id}");

        return self::SUCCESS;
    }
}
