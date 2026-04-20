<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales\EstimateResource\Pages;

use Erpsaas\Core\Enums\Accounting\EstimateStatus;
use Erpsaas\Sales\Filament\Company\Resources\Sales\EstimateResource;
use Erpsaas\Sales\Filament\Company\Resources\Sales\EstimateResource\Widgets;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListEstimates extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = EstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\EstimateOverview::make(),
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
                    $query->active();
                }),

            'draft' => Tab::make()
                ->label('Draft')
                ->modifyQueryUsing(function (Builder $query) {
                    $query->where('status', EstimateStatus::Draft);
                }),
        ];
    }
}
