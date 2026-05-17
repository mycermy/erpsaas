<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryAdjustmentResource\Pages;

use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\Concerns\HasOperationsTopSubNavigation;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryAdjustmentResource;
use Zrm\Inventory\Models\Warehouse;
use Zrm\Inventory\Services\InventoryAdjustmentImportService;

class ListInventoryAdjustments extends ListRecords
{
    use HasOperationsTopSubNavigation;

    protected static string $resource = InventoryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('downloadInventoryTemplate')
                ->label('Download Inventory Template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $company = filament()->getTenant();

                    if (! $company) {
                        throw ValidationException::withMessages([
                            'company_id' => 'No active company tenant found for template download.',
                        ]);
                    }

                    return app(InventoryAdjustmentImportService::class)->streamInventoryTemplate($company);
                }),
            Actions\Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->modalHeading('Bulk Inventory Adjustment Import')
                ->modalDescription('Import end-of-year stock take counts using the strict CSV template.')
                ->form([
                    Forms\Components\Select::make('warehouse_id')
                        ->label('Warehouse')
                        ->options(function () {
                            $companyId = filament()->getTenant()?->id;

                            return Warehouse::query()
                                ->where('company_id', $companyId)
                                ->where('active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->required()
                        ->searchable(),

                    Forms\Components\DatePicker::make('adjustment_date')
                        ->required()
                        ->default(now()),

                    Forms\Components\Select::make('unknown_sku_strategy')
                        ->label('Unknown SKU Handling')
                        ->options([
                            InventoryAdjustmentImportService::UNKNOWN_SKU_STRATEGY_FLAG => 'Flag as error (do not import row)',
                            InventoryAdjustmentImportService::UNKNOWN_SKU_STRATEGY_AUTO_CREATE => 'Auto-create inactive inventory item',
                        ])
                        ->default(InventoryAdjustmentImportService::UNKNOWN_SKU_STRATEGY_FLAG)
                        ->required(),

                    Forms\Components\FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->disk('local')
                        ->directory('imports/inventory-adjustments')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required()
                        ->helperText('Required header order: sku,item_name,quantity_counted,reason'),
                ])
                ->action(function (array $data) {
                    $company = filament()->getTenant();
                    $userId = Filament::auth()->user()?->getAuthIdentifier();

                    if (! $company || ! $userId) {
                        throw ValidationException::withMessages([
                            'company_id' => 'No active company tenant found for import.',
                        ]);
                    }

                    $warehouse = Warehouse::query()
                        ->where('company_id', $company->id)
                        ->findOrFail((int) $data['warehouse_id']);

                    $import = app(InventoryAdjustmentImportService::class)->importFromCsv(
                        company: $company,
                        warehouse: $warehouse,
                        csvPath: $data['csv_file'],
                        importedBy: $userId,
                        adjustmentDate: $data['adjustment_date'],
                        unknownSkuStrategy: $data['unknown_sku_strategy']
                    );

                    Notification::make()
                        ->title('Inventory CSV import completed')
                        ->body("Processed {$import->processed_rows}/{$import->total_rows} rows. Successful: {$import->successful_rows}, Failed: {$import->failed_rows}.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
