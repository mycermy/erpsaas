<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports;

use Erpsaas\Core\Contracts\ExportableReport;
use Erpsaas\Core\DTO\ReportDTO;
use Erpsaas\Core\Enums\Accounting\DocumentEntityType;
use Erpsaas\Core\Services\ExportService;
use Erpsaas\Core\Services\ReportService;
use Erpsaas\Core\Support\Column;
use Erpsaas\Core\Transformers\EntityBalanceSummaryReportTransformer;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Schemas\Components\FusedGroup;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class BaseEntityBalanceSummaryReportPage extends BaseReportPage
{
    protected string $view = 'filament.company.pages.reports.detailed-report';

    protected ReportService $reportService;

    protected ExportService $exportService;

    abstract protected function getEntityType(): DocumentEntityType;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    public function getTable(): array
    {
        return [
            Column::make('entity_name')
                ->label($this->getEntityType()->getLabel())
                ->alignment(Alignment::Left),
            Column::make('total_balance')
                ->label('Total')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('paid_balance')
                ->label('Paid')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('unpaid_balance')
                ->label('Unpaid')
                ->toggleable()
                ->alignment(Alignment::Right),
        ];
    }

    public function filtersForm(Schema $form): Schema
    {
        return $form
            ->inlineLabel()
            ->columns()
            ->schema([
                $this->getDateRangeFormComponent(),
                FusedGroup::make([
                    $this->getStartDateFormComponent(),
                    $this->getEndDateFormComponent(),
                ])->hiddenLabel(),
            ]);
    }

    protected function buildReport(array $columns): ReportDTO
    {
        return $this->reportService->buildEntityBalanceSummaryReport(
            startDate: $this->getFormattedStartDate(),
            endDate: $this->getFormattedEndDate(),
            entityType: $this->getEntityType(),
            columns: $columns
        );
    }

    protected function getTransformer(ReportDTO $reportDTO): ExportableReport
    {
        return new EntityBalanceSummaryReportTransformer($reportDTO, $this->getEntityType());
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv(
            $this->company,
            $this->report,
            $this->getFilterState('startDate'),
            $this->getFilterState('endDate')
        );
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf(
            $this->company,
            $this->report,
            $this->getFilterState('startDate'),
            $this->getFilterState('endDate')
        );
    }
}
