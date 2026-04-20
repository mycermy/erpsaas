<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource\Pages;

use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource;
use Erpsaas\Core\Models\Core\Department;
use Filament\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ListRecords;

class ListDepartments extends ListRecords
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(Department::query()->count()),
            'main' => Tab::make('Main')
                ->badge(Department::query()->whereParentId(null)->count())
                ->modifyQueryUsing(static function ($query) {
                    $query->whereParentId(null);
                }),
            'children' => Tab::make('Children')
                ->badge(Department::query()->whereNotNull('parent_id')->count())
                ->modifyQueryUsing(static function ($query) {
                    $query->whereNotNull('parent_id');
                }),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
