<?php

namespace App\Filament\Company\Resources\Purchases\BillResource\Pages;

use App\Enums\Accounting\DocumentType;
use App\Filament\Company\Resources\Purchases\BillResource;
use App\Filament\Company\Resources\Purchases\VendorResource;
use App\Filament\Infolists\Components\BannerEntry;
use App\Filament\Infolists\Components\DocumentPreview;
use App\Models\Accounting\Bill;
use Filament\Actions;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\HtmlString;

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
                BannerEntry::make('inactiveAdjustments')
                    ->label('Inactive adjustments')
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn (Bill $record) => $record->hasInactiveAdjustments())
                    ->columnSpanFull()
                    ->description(function (Bill $record) {
                        $inactiveAdjustments = collect();

                        foreach ($record->lineItems as $lineItem) {
                            foreach ($lineItem->adjustments as $adjustment) {
                                if ($adjustment->isInactive() && $inactiveAdjustments->doesntContain($adjustment->name)) {
                                    $inactiveAdjustments->push($adjustment->name);
                                }
                            }
                        }

                        $adjustmentsList = $inactiveAdjustments->map(static function ($name) {
                            return "<span class='font-medium'>{$name}</span>";
                        })->join(', ');

                        $output = "<p class='text-sm'>This bill contains inactive adjustments: {$adjustmentsList}</p>";

                        return new HtmlString($output);
                    }),
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
                                TextEntry::make('amount_due')
                                    ->label('Amount due')
                                    ->currency(static fn (Bill $record) => $record->currency_code),
                                TextEntry::make('due_date')
                                    ->label('Due')
                                    ->asRelativeDay(),
                                TextEntry::make('date')
                                    ->label('Date')
                                    ->date(),
                                TextEntry::make('paid_at')
                                    ->label('Paid at')
                                    ->date(),
                            ])->columnSpan(1),
                        DocumentPreview::make()
                            ->type(DocumentType::Bill),
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
