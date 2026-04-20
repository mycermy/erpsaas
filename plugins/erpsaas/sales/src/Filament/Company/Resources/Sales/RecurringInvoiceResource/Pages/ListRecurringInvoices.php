<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales\RecurringInvoiceResource\Pages;

use Erpsaas\Core\Concerns\HasTabSpecificColumnToggles;
use Erpsaas\Core\Enums\Accounting\RecurringInvoiceStatus;
use Erpsaas\Sales\Filament\Company\Resources\Sales\RecurringInvoiceResource;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListRecurringInvoices extends ListRecords
{
    use HasTabSpecificColumnToggles;

    protected static string $resource = RecurringInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return 'max-w-full';
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('All'),

            'active' => Tab::make()
                ->label('Active')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('status', RecurringInvoiceStatus::Active);
                }),

            'draft' => Tab::make()
                ->label('Draft')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('status', RecurringInvoiceStatus::Draft);
                }),
        ];
    }
}
