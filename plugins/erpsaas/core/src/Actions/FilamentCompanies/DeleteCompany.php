<?php

namespace Erpsaas\Core\Actions\FilamentCompanies;

use Erpsaas\Core\Models\Company;
use Wallo\FilamentCompanies\Contracts\DeletesCompanies;

class DeleteCompany implements DeletesCompanies
{
    /**
     * Delete the given company.
     */
    public function delete(Company $company): void
    {
        $company->purge();
    }
}
