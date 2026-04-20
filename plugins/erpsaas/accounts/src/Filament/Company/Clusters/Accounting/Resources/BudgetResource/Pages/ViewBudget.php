<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Resources\BudgetResource\Pages;

use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Resources\BudgetResource;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewBudget extends ViewRecord
{
    protected static string $resource = BudgetResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return '8xl';
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    protected function getAllRelationManagers(): array
    {
        return [
            BudgetResource\RelationManagers\BudgetItemsRelationManager::class,
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form->schema([]);
    }

    public function infolist(Schema $infolist): Schema
    {
        return $infolist->schema([]);
    }
}
