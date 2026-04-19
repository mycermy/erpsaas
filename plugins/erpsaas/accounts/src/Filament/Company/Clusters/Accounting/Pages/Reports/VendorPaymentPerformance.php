<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports;

use Erpsaas\Core\Enums\Accounting\DocumentEntityType;

class VendorPaymentPerformance extends BaseEntityPaymentPerformanceReportPage
{
    protected function getEntityType(): DocumentEntityType
    {
        return DocumentEntityType::Vendor;
    }
}
