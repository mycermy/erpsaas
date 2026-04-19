<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class Accounting extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('Accounting');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Accounting');
    }
}
