<?php

namespace Erpsaas\Sales\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class Sales extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('Sales');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Sales');
    }
}
