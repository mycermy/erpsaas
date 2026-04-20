<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports;

use Erpsaas\Core\Contracts\ExportableReport;
use Erpsaas\Core\DTO\ReportDTO;
use Erpsaas\Core\Filament\Forms\Components\DateRangeSelect;
use Erpsaas\Core\Services\ExportService;
use Erpsaas\Core\Services\ReportService;
use Erpsaas\Core\Support\Column;
use Erpsaas\Core\Transformers\TrialBalanceReportTransformer;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrialBalance extends BaseReportPage
{
    protected string $view = 'filament.company.pages.reports.trial-balance';

    protected ReportService $reportService;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    protected function initializeDefaultFilters(): void
    {
        if (empty($this->getFilterState('reportType'))) {
            $this->setFilterState('reportType', 'standard');
        }
    }

    public function getTable(): array
    {
        return [
            Column::make('account_code')
                ->label('ACCOUNT CODE')
                ->toggleable(isToggledHiddenByDefault: true)
                ->alignment(Alignment::Left),
            Column::make('account_name')
                ->label('ACCOUNTS')
                ->alignment(Alignment::Left),
            Column::make('debit_balance')
                ->label('DEBIT')
                ->alignment(Alignment::Right),
            Column::make('credit_balance')
                ->label('CREDIT')
                ->alignment(Alignment::Right),
        ];
    }

    public function filtersForm(Schema $form): Schema
    {
        return $form
            ->columns(4)
            ->schema([
                Select::make('reportType')
                    ->label('Report type')
                    ->options([
                        'standard' => 'Standard',
                        'postClosing' => 'Post-Closing',
                    ])
                    ->selectablePlaceholder(false),
                DateRangeSelect::make('dateRange')
                    ->label('As of')
                    ->selectablePlaceholder(false)
                    ->endDateField('asOfDate'),
                $this->getAsOfDateFormComponent(),
            ]);
    }

    protected function buildReport(array $columns): ReportDTO
    {
        return $this->reportService->buildTrialBalanceReport($this->getFilterState('reportType'), $this->getFormattedAsOfDate(), $columns);
    }

    protected function getTransformer(ReportDTO $reportDTO): ExportableReport
    {
        return new TrialBalanceReportTransformer($reportDTO);
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv($this->company, $this->report, endDate: $this->getFilterState('asOfDate'));
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf($this->company, $this->report, endDate: $this->getFilterState('asOfDate'));
    }
}
