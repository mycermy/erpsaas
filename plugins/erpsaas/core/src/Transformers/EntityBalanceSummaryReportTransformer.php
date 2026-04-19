<?php

namespace Erpsaas\Core\Transformers;

use Erpsaas\Core\DTO\EntityReportDTO;
use Erpsaas\Core\DTO\ReportCategoryDTO;
use Erpsaas\Core\DTO\ReportDTO;
use Erpsaas\Core\Enums\Accounting\DocumentEntityType;

class EntityBalanceSummaryReportTransformer extends BaseReportTransformer
{
    public function __construct(
        ReportDTO $report,
        private readonly DocumentEntityType $entityType,
    ) {
        parent::__construct($report);
    }

    public function getTitle(): string
    {
        return $this->entityType->getBalanceSummaryReportTitle();
    }

    /**
     * @return ReportCategoryDTO[]
     */
    public function getCategories(): array
    {
        $categories = [];

        foreach ($this->report->categories as $categoryName => $category) {
            $data = array_map(function (EntityReportDTO $entity) {
                $row = [];

                foreach ($this->getColumns() as $column) {
                    $row[$column->getName()] = match ($column->getName()) {
                        'entity_name' => [
                            'name' => $entity->name,
                            'id' => $entity->id,
                        ],
                        'total_balance' => $entity->balance->totalBalance,
                        'paid_balance' => $entity->balance->paidBalance,
                        'unpaid_balance' => $entity->balance->unpaidBalance,
                        default => '',
                    };
                }

                return $row;
            }, $category);

            $categories[] = new ReportCategoryDTO(
                header: null,
                data: $data,
                summary: null,
            );
        }

        return $categories;
    }

    public function getOverallTotals(): array
    {
        $totals = [];

        foreach ($this->getColumns() as $column) {
            $totals[$column->getName()] = match ($column->getName()) {
                'entity_name' => 'Total',
                'total_balance' => $this->report->entityBalanceTotal->totalBalance,
                'paid_balance' => $this->report->entityBalanceTotal->paidBalance,
                'unpaid_balance' => $this->report->entityBalanceTotal->unpaidBalance,
                default => '',
            };
        }

        return $totals;
    }
}
