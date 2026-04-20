<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports;

use Erpsaas\Core\Contracts\ExportableReport;
use Erpsaas\Core\DTO\ReportDTO;
use Erpsaas\Core\Filament\Company\Pages\Concerns\HasReportTabs;
use Erpsaas\Core\Filament\Forms\Components\DateRangeSelect;
use Erpsaas\Core\Services\ExportService;
use Erpsaas\Core\Services\ReportService;
use Erpsaas\Core\Support\Column;
use Erpsaas\Core\Transformers\BalanceSheetReportTransformer;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BalanceSheet extends BaseReportPage
{
    use HasReportTabs;

    protected string $view = 'filament.company.pages.reports.balance-sheet';

    protected ReportService $reportService;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
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
            Column::make('ending_balance')
                ->label($this->getDisplayAsOfDate())
                ->alignment(Alignment::Right),
        ];
    }

    public function filtersForm(Schema $form): Schema
    {
        return $form
            ->inlineLabel()
            ->columns(3)
            ->schema([
                DateRangeSelect::make('dateRange')
                    ->label('As of')
                    ->selectablePlaceholder(false)
                    ->endDateField('asOfDate'),
                $this->getAsOfDateFormComponent()
                    ->hiddenLabel()
                    ->extraFieldWrapperAttributes([]),
            ]);
    }

    protected function buildReport(array $columns): ReportDTO
    {
        return $this->reportService->buildBalanceSheetReport($this->getFormattedAsOfDate(), $columns);
    }

    protected function getTransformer(ReportDTO $reportDTO): ExportableReport
    {
        return new BalanceSheetReportTransformer($reportDTO);
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv($this->company, $this->report, endDate: $this->getFilterState('asOfDate'), activeTab: $this->getActiveTab());
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf($this->company, $this->report, endDate: $this->getFilterState('asOfDate'), activeTab: $this->getActiveTab());
    }
}
