<?php

namespace Awcodes\TableRepeater;

use Filament\Forms\Components\Repeater\TableColumn;

class Header extends TableColumn
{
    public function isRequired(): bool
    {
        return $this->isMarkedAsRequired();
    }
}
