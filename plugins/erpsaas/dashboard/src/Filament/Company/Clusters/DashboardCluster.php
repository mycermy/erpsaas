<?php

namespace Erpsaas\Dashboard\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class DashboardCluster extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return __('Dashboard');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Dashboard');
    }
}
