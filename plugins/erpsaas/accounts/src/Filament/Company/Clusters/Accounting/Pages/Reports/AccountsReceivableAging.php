<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports;

use Erpsaas\Core\Enums\Accounting\DocumentEntityType;

class AccountsReceivableAging extends BaseAgingReportPage
{
    protected function getEntityType(): DocumentEntityType
    {
        return DocumentEntityType::Client;
    }
}
