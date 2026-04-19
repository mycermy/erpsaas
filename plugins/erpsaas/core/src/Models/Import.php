<?php

namespace Erpsaas\Core\Models;

use Erpsaas\Core\Concerns\CompanyOwned;
use Filament\Actions\Imports\Models\Import as BaseImport;

class Import extends BaseImport
{
    use CompanyOwned;
}
