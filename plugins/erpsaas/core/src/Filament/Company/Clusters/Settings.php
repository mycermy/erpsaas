<?php

namespace Erpsaas\Core\Filament\Company\Clusters;

use Filament\Clusters\Cluster;

class Settings extends Cluster
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 100;

    public static function getNavigationLabel(): string
    {
        return __('Settings');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }
}
