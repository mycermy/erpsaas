<?php

namespace Erpsaas\Purchases\Filament\Company\Resources\Purchases\BillResource\Pages;

use Erpsaas\Accounts\Models\Accounting\Bill;
use Erpsaas\Core\Enums\Accounting\DocumentType;
use Erpsaas\Core\Filament\Infolists\Components\DocumentPreview;
use Erpsaas\Purchases\Filament\Company\Resources\Purchases\BillResource;
use Erpsaas\Purchases\Filament\Company\Resources\Purchases\VendorResource;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;

class ViewBill extends ViewRecord
{
    protected static string $resource = BillResource::class;

    protected $listeners = [
        'refresh' => '$refresh',
    ];

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('Edit bill')
                ->outlined(),
            Actions\ActionGroup::make([
                Actions\ActionGroup::make([
                    Bill::getPrintDocumentAction(),
                    Bill::getPdfDocumentAction(),
                    Bill::getReplicateAction(),
                ])->dropdown(false),
                Actions\DeleteAction::make(),
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
                Section::make('Bill Details')
                    ->columns(4)
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('bill_number')
                                    ->label('Bill #'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('vendor.name')
                                    ->label('Vendor')
                                    ->url(static fn (Bill $record) => $record->vendor_id ? VendorResource::getUrl('view', ['record' => $record->vendor_id]) : null)
                                    ->link(),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->currency(static fn (Bill $record) => $record->currency_code),
                                TextEntry::make('amount_due')
                                    ->label('Amount due')
                                    ->currency(static fn (Bill $record) => $record->currency_code),
                                TextEntry::make('date')
                                    ->label('Date')
                                    ->date(),
                                TextEntry::make('due_date')
                                    ->label('Due')
                                    ->asRelativeDay(),
                                TextEntry::make('paid_at')
                                    ->label('Paid at')
                                    ->date(),
                            ])->columnSpan(1),
                        DocumentPreview::make()
                            ->type(DocumentType::Bill)
                            ->preview(),
                    ]),
            ]);
    }

    protected function getAllRelationManagers(): array
    {
        return [
            BillResource\RelationManagers\PaymentsRelationManager::class,
        ];
    }
}
