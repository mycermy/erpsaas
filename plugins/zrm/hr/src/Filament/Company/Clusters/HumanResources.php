<?php

namespace Zrm\Hr\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class HumanResources extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('Human Resources');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Human Resources');
    }
}
