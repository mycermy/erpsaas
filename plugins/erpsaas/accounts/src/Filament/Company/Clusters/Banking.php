<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class Banking extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('Banking');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Accounting');
    }
}
