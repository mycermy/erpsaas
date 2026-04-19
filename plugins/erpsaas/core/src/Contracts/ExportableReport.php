<?php

namespace Erpsaas\Core\Contracts;

use Erpsaas\Core\DTO\ReportCategoryDTO;
use Erpsaas\Core\Support\Column;

interface ExportableReport
{
    public function getTitle(): string;

    public function getHeaders(): array;

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array;

    public function getOverallTotals(): array;

    /**
     * @return Column[]
     */
    public function getColumns(): array;

    public function getPdfView(): string;

    public function getAlignmentClass(string $columnName): string;

    public function getStartDate(): ?string;

    public function getEndDate(): ?string;
}
