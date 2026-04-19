<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Resources\BudgetResource\Pages;

use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Resources\BudgetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgets extends ListRecords
{
    protected static string $resource = BudgetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
