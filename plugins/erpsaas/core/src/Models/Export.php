<?php

namespace Erpsaas\Core\Models;

use Erpsaas\Core\Concerns\CompanyOwned;
use Filament\Actions\Exports\Models\Export as BaseExport;

class Export extends BaseExport
{
    use CompanyOwned;
}
