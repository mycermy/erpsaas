<?php

namespace Zrm\Inventory\Services;

use Erpsaas\Core\Enums\Common\OfferingType;
use Erpsaas\Core\Models\Common\Offering;
use Erpsaas\Core\Models\Company;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Zrm\Inventory\Enums\AdjustmentStatus;
use Zrm\Inventory\Enums\AdjustmentType;
use Zrm\Inventory\Models\InventoryAdjustment;
use Zrm\Inventory\Models\InventoryAdjustmentImport;
use Zrm\Inventory\Models\InventoryAdjustmentImportRow;
use Zrm\Inventory\Models\InventoryItem;
use Zrm\Inventory\Models\InventoryStockLevel;
use Zrm\Inventory\Models\Warehouse;

class InventoryAdjustmentImportService
{
    public const UNKNOWN_SKU_STRATEGY_FLAG = 'flag';

    public const UNKNOWN_SKU_STRATEGY_AUTO_CREATE = 'auto_create';

    /**
     * @var array<int, string>
     */
    public const TEMPLATE_HEADERS = [
        'sku',
        'item_name',
        'quantity_counted',
        'reason',
    ];

    /**
     * @var array<int, string>
     */
    public const INVENTORY_TEMPLATE_HEADERS = [
        'warehouse_id',
        'warehouse_name',
        'item_category',
        'brand',
        'sku',
        'item_name',
        'current_quantity',
        'quantity_counted',
        'reason',
    ];

    /**
     * @return array<int, string>
     */
    public function expectedHeaders(): array
    {
        return self::TEMPLATE_HEADERS;
    }

    public function templateExampleAsCsv(): string
    {
        return implode(',', self::TEMPLATE_HEADERS) . PHP_EOL
            . 'LAPTOP-001,Laptop Computer,25,EOY stock take count' . PHP_EOL
            . 'MOUSE-001,Wireless Mouse,120,EOY stock take count' . PHP_EOL
            . 'NEW-SKU-001,Uncatalogued Item,8,Found physically during count' . PHP_EOL;
    }

    /**
     * @return array<int, string>
     */
    public function inventoryTemplateHeaders(): array
    {
        return self::INVENTORY_TEMPLATE_HEADERS;
    }

    /**
     * @return LazyCollection<int, array<string, string|int>>
     */
    public function inventoryTemplateRows(Company $company): LazyCollection
    {
        return DB::table('inventory_stock_levels as stock_levels')
            ->join('inventory_items', 'inventory_items.id', '=', 'stock_levels.inventory_item_id')
            ->join('offerings', 'offerings.id', '=', 'inventory_items.offering_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('stock_levels.company_id', $company->id)
            ->where('inventory_items.active', true)
            ->select([
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                DB::raw("COALESCE(offerings.type, '') as item_category"),
                DB::raw("'' as brand"),
                'inventory_items.sku',
                'offerings.name as item_name',
                'stock_levels.quantity_on_hand as current_quantity',
            ])
            ->orderBy('warehouses.name')
            ->orderBy('warehouses.id')
            ->orderBy('item_category')
            ->orderBy('brand')
            ->orderBy('inventory_items.sku')
            ->cursor()
            ->map(static function (object $row): array {
                return [
                    'warehouse_id' => (int) $row->warehouse_id,
                    'warehouse_name' => (string) $row->warehouse_name,
                    'item_category' => (string) $row->item_category,
                    'brand' => (string) $row->brand,
                    'sku' => (string) $row->sku,
                    'item_name' => (string) $row->item_name,
                    'current_quantity' => (int) $row->current_quantity,
                    'quantity_counted' => '',
                    'reason' => '',
                ];
            });
    }

    public function streamInventoryTemplate(Company $company): StreamedResponse
    {
        $headers = $this->inventoryTemplateHeaders();
        $rows = $this->inventoryTemplateRows($company);

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'wb');

            if (! is_resource($handle)) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['warehouse_id'],
                    $row['warehouse_name'],
                    $row['item_category'],
                    $row['brand'],
                    $row['sku'],
                    $row['item_name'],
                    $row['current_quantity'],
                    $row['quantity_counted'],
                    $row['reason'],
                ]);
            }

            fclose($handle);
        }, 'inventory-template-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importFromCsv(
        Company $company,
        Warehouse $warehouse,
        string $csvPath,
        int $importedBy,
        ?string $adjustmentDate = null,
        string $unknownSkuStrategy = self::UNKNOWN_SKU_STRATEGY_FLAG
    ): InventoryAdjustmentImport {
        if (! in_array($unknownSkuStrategy, [self::UNKNOWN_SKU_STRATEGY_FLAG, self::UNKNOWN_SKU_STRATEGY_AUTO_CREATE], true)) {
            throw ValidationException::withMessages([
                'unknown_sku_strategy' => 'Unknown SKU strategy must be one of: flag, auto_create.',
            ]);
        }

        if (! Storage::disk('local')->exists($csvPath)) {
            throw ValidationException::withMessages([
                'csv_file' => 'Uploaded CSV file was not found on disk.',
            ]);
        }

        $import = InventoryAdjustmentImport::create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'imported_by' => $importedBy,
            'file_name' => basename($csvPath),
            'status' => 'processing',
            'unknown_sku_strategy' => $unknownSkuStrategy,
            'started_at' => now(),
        ]);

        try {
            $processed = DB::transaction(function () use ($company, $warehouse, $csvPath, $importedBy, $adjustmentDate, $unknownSkuStrategy, $import) {
                $parsed = $this->parseCsvRows($csvPath);

                $rowLogs = [];
                $adjustmentItems = [];
                $totalRows = 0;
                $successfulRows = 0;
                $failedRows = 0;
                $errors = [];

                foreach ($parsed['rows'] as $parsedRow) {
                    $totalRows++;

                    $rowNumber = $parsedRow['row_number'];
                    $row = $parsedRow['row'];
                    $rowError = $this->validateImportRow($row);

                    if ($rowError !== null) {
                        $failedRows++;
                        $errors[] = "Row {$rowNumber}: {$rowError}";
                        $rowLogs[] = $this->makeRowLog(
                            rowNumber: $rowNumber,
                            row: $row,
                            status: 'failed',
                            action: 'flagged',
                            errorMessage: $rowError
                        );

                        continue;
                    }

                    $inventoryItem = InventoryItem::query()
                        ->where('company_id', $company->id)
                        ->where('sku', $row['sku'])
                        ->first();

                    $action = 'adjusted';

                    if (! $inventoryItem) {
                        if ($unknownSkuStrategy === self::UNKNOWN_SKU_STRATEGY_FLAG) {
                            $failedRows++;
                            $message = "Unknown SKU {$row['sku']}";
                            $errors[] = "Row {$rowNumber}: {$message}";

                            $rowLogs[] = $this->makeRowLog(
                                rowNumber: $rowNumber,
                                row: $row,
                                status: 'failed',
                                action: 'flagged',
                                errorMessage: $message
                            );

                            continue;
                        }

                        $inventoryItem = $this->createInventoryItemFromUnknownSku(
                            company: $company,
                            sku: $row['sku'],
                            itemName: $row['item_name'],
                            createdBy: $importedBy
                        );

                        $action = 'created_item';
                    }

                    $stockLevel = InventoryStockLevel::query()
                        ->where('company_id', $company->id)
                        ->where('inventory_item_id', $inventoryItem->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->first();

                    $quantityBefore = (int) ($stockLevel?->quantity_on_hand ?? 0);
                    $quantityCounted = (int) $row['quantity_counted'];
                    $quantityAdjusted = $quantityCounted - $quantityBefore;

                    if ($quantityAdjusted === 0) {
                        $successfulRows++;
                        $rowLogs[] = $this->makeRowLog(
                            rowNumber: $rowNumber,
                            row: $row,
                            status: 'skipped',
                            action: 'skipped',
                            quantityBefore: $quantityBefore,
                            quantityCounted: $quantityCounted,
                            quantityAdjusted: 0
                        );

                        continue;
                    }

                    $unitCost = (int) ($stockLevel?->getRawOriginal('average_cost') ?? 0);

                    $adjustmentItems[] = [
                        'company_id' => $company->id,
                        'inventory_item_id' => $inventoryItem->id,
                        'quantity_before' => $quantityBefore,
                        'quantity_after' => $quantityCounted,
                        'quantity_adjusted' => $quantityAdjusted,
                        'unit_cost' => $unitCost,
                        'reason' => $row['reason'] !== '' ? $row['reason'] : 'Bulk stock take CSV import',
                    ];

                    $successfulRows++;
                    $rowLogs[] = $this->makeRowLog(
                        rowNumber: $rowNumber,
                        row: $row,
                        status: 'success',
                        action: $action,
                        quantityBefore: $quantityBefore,
                        quantityCounted: $quantityCounted,
                        quantityAdjusted: $quantityAdjusted
                    );
                }

                $adjustment = null;
                if (! empty($adjustmentItems)) {
                    $adjustment = InventoryAdjustment::create([
                        'company_id' => $company->id,
                        'warehouse_id' => $warehouse->id,
                        'adjustment_number' => 'ADJ-IMP-' . now()->format('ymd-His') . '-' . random_int(100, 999),
                        'adjustment_date' => $adjustmentDate ?? now()->toDateString(),
                        'status' => AdjustmentStatus::Draft,
                        'adjustment_type' => AdjustmentType::Stocktake,
                        'reason' => 'Bulk import from CSV stock take',
                        'created_by' => $importedBy,
                        'updated_by' => $importedBy,
                    ]);

                    $adjustment->items()->createMany($adjustmentItems);
                    $adjustment->approve($importedBy);
                }

                if (! empty($rowLogs)) {
                    InventoryAdjustmentImportRow::insert(
                        array_map(function (array $rowLog) use ($import) {
                            $payload = $rowLog['payload'] ?? null;

                            return [
                                ...$rowLog,
                                'payload' => is_array($payload) ? json_encode($payload, JSON_THROW_ON_ERROR) : $payload,
                                'import_id' => $import->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }, $rowLogs)
                    );
                }

                $status = 'completed';
                if ($failedRows > 0 && $successfulRows > 0) {
                    $status = 'completed_with_errors';
                }

                if ($failedRows > 0 && $successfulRows === 0) {
                    $status = 'failed';
                }

                $import->update([
                    'adjustment_id' => $adjustment?->id,
                    'status' => $status,
                    'total_rows' => $totalRows,
                    'processed_rows' => $successfulRows + $failedRows,
                    'successful_rows' => $successfulRows,
                    'failed_rows' => $failedRows,
                    'error_summary' => Arr::take($errors, 50),
                    'finished_at' => now(),
                ]);

                return $import;
            });

            return $processed->fresh(['adjustment']);
        } catch (\Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_summary' => [
                    $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array{row_number: int, row: array<string, string>}>}
     */
    protected function parseCsvRows(string $csvPath): array
    {
        $stream = Storage::disk('local')->readStream($csvPath);

        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'csv_file' => 'Unable to read uploaded CSV file.',
            ]);
        }

        $headerRow = fgetcsv($stream);
        if (! is_array($headerRow)) {
            fclose($stream);

            throw ValidationException::withMessages([
                'csv_file' => 'CSV file is empty.',
            ]);
        }

        $headers = array_map(static fn($value) => trim((string) $value), $headerRow);
        if ($headers !== self::TEMPLATE_HEADERS) {
            fclose($stream);

            throw ValidationException::withMessages([
                'csv_file' => 'Invalid CSV template headers. Expected exact order: ' . implode(',', self::TEMPLATE_HEADERS),
            ]);
        }

        $rows = [];
        $rowNumber = 2;

        while (($raw = fgetcsv($stream)) !== false) {
            $isBlankRow = count($raw) === 1 && trim((string) ($raw[0] ?? '')) === '';
            if ($isBlankRow) {
                $rowNumber++;

                continue;
            }

            if (count($raw) !== count(self::TEMPLATE_HEADERS)) {
                fclose($stream);

                throw ValidationException::withMessages([
                    'csv_file' => "Row {$rowNumber} does not match the required template column count.",
                ]);
            }

            $rows[] = [
                'row_number' => $rowNumber,
                'row' => array_combine(
                    self::TEMPLATE_HEADERS,
                    array_map(static fn($value) => trim((string) $value), $raw)
                ),
            ];

            $rowNumber++;
        }

        fclose($stream);

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    protected function validateImportRow(array $row): ?string
    {
        if ($row['sku'] === '') {
            return 'SKU is required.';
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $row['sku'])) {
            return 'SKU format is invalid. Allowed characters are letters, numbers, dots, underscores, and dashes.';
        }

        if ($row['item_name'] === '') {
            return 'item_name is required.';
        }

        if ($row['quantity_counted'] === '' || ! ctype_digit($row['quantity_counted'])) {
            return 'quantity_counted must be a non-negative integer.';
        }

        return null;
    }

    protected function createInventoryItemFromUnknownSku(Company $company, string $sku, string $itemName, int $createdBy): InventoryItem
    {
        $offering = new Offering;
        $offering->company_id = $company->id;
        $offering->name = $itemName;
        $offering->description = 'Auto-created from inventory adjustment CSV import';
        $offering->type = OfferingType::Product;
        $offering->price = 0;
        $offering->sellable = false;
        $offering->purchasable = false;
        $offering->created_by = $createdBy;
        $offering->updated_by = $createdBy;
        $offering->stockable = true;
        $offering->save();

        return InventoryItem::create([
            'company_id' => $company->id,
            'offering_id' => $offering->id,
            'sku' => $sku,
            'track_method' => 'fifo',
            'reorder_level' => 0,
            'reorder_quantity' => 0,
            'track_batches' => false,
            'active' => false,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    protected function makeRowLog(
        int $rowNumber,
        array $row,
        string $status,
        string $action,
        ?string $errorMessage = null,
        ?int $quantityBefore = null,
        ?int $quantityCounted = null,
        ?int $quantityAdjusted = null
    ): array {
        return [
            'row_number' => $rowNumber,
            'sku' => $row['sku'] ?? null,
            'item_name' => $row['item_name'] ?? null,
            'quantity_before' => $quantityBefore,
            'quantity_counted' => $quantityCounted,
            'quantity_adjusted' => $quantityAdjusted,
            'status' => $status,
            'action' => $action,
            'error_message' => $errorMessage,
            'payload' => $row,
        ];
    }
}
