<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryAdjustmentResource\Pages;

use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Facades\Auth;
use Zrm\Inventory\Enums\AdjustmentStatus;
use Zrm\Inventory\Filament\Clusters\Operations\Resources\InventoryAdjustmentResource;
use Zrm\Inventory\Models\InventoryAdjustment;

class ViewInventoryAdjustment extends ViewRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit adjustment')
                ->outlined()
                ->visible(fn (InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft),
            Actions\ActionGroup::make([
                Actions\ActionGroup::make([
                    Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft)
                        ->action(function (InventoryAdjustment $record) {
                            $record->approve(Auth::id());
                            $this->refreshFormData([
                                'status',
                                'approved_at',
                                'approved_by',
                            ]);
                        }),
                    Actions\Action::make('cancel')
                        ->label('Cancel')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Adjustment')
                        ->modalDescription(fn (InventoryAdjustment $record) => $record->status === AdjustmentStatus::Approved
                            ? 'This adjustment has been approved and inventory movements have been created. Cancelling will reverse all inventory movements. Are you sure?'
                            : 'Are you sure you want to cancel this adjustment?')
                        ->visible(fn (InventoryAdjustment $record) => in_array($record->status, [AdjustmentStatus::Draft, AdjustmentStatus::Approved]))
                        ->action(function (InventoryAdjustment $record) {
                            $record->cancel();
                            $this->refreshFormData(['status']);
                        }),
                ])->dropdown(false),
                Actions\DeleteAction::make()
                    ->visible(fn (InventoryAdjustment $record) => $record->status === AdjustmentStatus::Draft),
            ])
                ->label('Actions')
                ->button()
                ->outlined()
                ->dropdownPlacement('bottom-end')
                ->icon('heroicon-m-chevron-down')
                ->iconPosition(IconPosition::After),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Adjustment Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('adjustment_number')
                            ->label('Reference Number')
                            ->size(TextEntry\TextEntrySize::Large)
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('adjustment_type')
                            ->label('Type')
                            ->badge()
                            ->tooltip(fn (InventoryAdjustment $record) => $record->adjustment_type->description()),
                        TextEntry::make('warehouse.name')
                            ->label('Warehouse')
                            ->icon('heroicon-o-building-storefront'),
                        TextEntry::make('adjustment_date')
                            ->label('Adjustment Date')
                            ->date()
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('createdBy.name')
                            ->label('Created By')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('approver.name')
                            ->label('Approved By')
                            ->icon('heroicon-o-user-circle')
                            ->placeholder('Not approved yet')
                            ->visible(fn (InventoryAdjustment $record) => $record->approved_at !== null),
                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime()
                            ->icon('heroicon-o-check-circle')
                            ->visible(fn (InventoryAdjustment $record) => $record->approved_at !== null),
                        TextEntry::make('reason')
                            ->label('Reason')
                            ->columnSpanFull()
                            ->placeholder('No reason provided'),
                    ]),

                Section::make('Adjustment Items')
                    ->description('Items included in this adjustment with quantity changes')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(5)
                                    ->schema([
                                        TextEntry::make('inventoryItem.offering.name')
                                            ->label('Item')
                                            ->weight('semibold'),
                                        TextEntry::make('inventoryItem.sku')
                                            ->label('SKU')
                                            ->color('gray'),
                                        TextEntry::make('quantity_before')
                                            ->label('Before')
                                            ->numeric()
                                            ->suffix(' units')
                                            ->color('gray'),
                                        TextEntry::make('quantity_after')
                                            ->label('After')
                                            ->numeric()
                                            ->suffix(' units')
                                            ->weight('medium'),
                                        TextEntry::make('quantity_adjusted')
                                            ->label('Adjustment')
                                            ->numeric()
                                            ->badge()
                                            ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray'))
                                            ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . $state . ' units'),
                                    ]),
                                TextEntry::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->money('MYR')
                                    ->placeholder('N/A'),
                                TextEntry::make('reason')
                                    ->label('Item Reason')
                                    ->placeholder('No specific reason')
                                    ->columnSpanFull(),

                                // Show batch allocations if they exist
                                RepeatableEntry::make('batchAllocations')
                                    ->label('Batch Allocations')
                                    ->contained(false)
                                    ->visible(fn ($record) => $record->batchAllocations->isNotEmpty())
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                TextEntry::make('inventoryBatch.batch_number')
                                                    ->label('Batch #')
                                                    ->icon('heroicon-o-cube'),
                                                TextEntry::make('quantity')
                                                    ->label('Quantity')
                                                    ->numeric()
                                                    ->suffix(' units'),
                                                TextEntry::make('unit_cost')
                                                    ->label('Unit Cost')
                                                    ->money('MYR'),
                                                TextEntry::make('total_cost')
                                                    ->label('Total Cost')
                                                    ->money('MYR')
                                                    ->weight('semibold'),
                                            ]),
                                    ]),
                            ])
                            ->contained()
                            ->columnSpanFull(),
                    ]),

                Section::make('Summary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('items_count')
                            ->label('Total Items')
                            ->state(fn (InventoryAdjustment $record) => $record->items->count())
                            ->icon('heroicon-o-clipboard-document-list'),
                        TextEntry::make('total_quantity_adjusted')
                            ->label('Total Quantity Change')
                            ->state(function (InventoryAdjustment $record) {
                                $total = $record->items->sum('quantity_adjusted');

                                return ($total > 0 ? '+' : '') . $total . ' units';
                            })
                            ->color(fn (InventoryAdjustment $record) => $record->items->sum('quantity_adjusted') > 0 ? 'success' : 'danger')
                            ->icon('heroicon-o-chart-bar'),
                    ]),
            ]);
    }
}
