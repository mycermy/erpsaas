<?php

namespace Erpsaas\Purchases\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class Purchases extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('Purchases');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Purchases');
    }
}
