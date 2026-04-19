<?php

namespace Erpsaas\Core\Contracts;

use Erpsaas\Core\DTO\ReportCategoryDTO;
use Erpsaas\Core\Support\Column;

interface HasSummaryReport
{
    /**
     * @return Column[]
     */
    public function getSummaryColumns(): array;

    public function getSummaryHeaders(): array;

    /**
     * @return ReportCategoryDTO[]
     */
    public function getSummaryCategories(): array;

    public function getSummaryOverallTotals(): array;

    public function getSummaryPdfView(): string;
}
