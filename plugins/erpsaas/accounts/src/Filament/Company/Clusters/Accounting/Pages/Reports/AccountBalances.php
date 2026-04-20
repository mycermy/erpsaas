<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports;

use Erpsaas\Core\Contracts\ExportableReport;
use Erpsaas\Core\DTO\ReportDTO;
use Erpsaas\Core\Services\ExportService;
use Erpsaas\Core\Services\ReportService;
use Erpsaas\Core\Support\Column;
use Erpsaas\Core\Transformers\AccountBalanceReportTransformer;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Schemas\Components\FusedGroup;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountBalances extends BaseReportPage
{
    protected string $view = 'filament.company.pages.reports.detailed-report';

    protected ReportService $reportService;

    protected ExportService $exportService;

    public function boot(ReportService $reportService, ExportService $exportService): void
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }

    /**
     * @return array<Column>
     */
    public function getTable(): array
    {
        return [
            Column::make('account_code')
                ->label('ACCOUNT CODE')
                ->toggleable(isToggledHiddenByDefault: true)
                ->alignment(Alignment::Left),
            Column::make('account_name')
                ->label('ACCOUNT')
                ->alignment(Alignment::Left),
            Column::make('starting_balance')
                ->label('STARTING BALANCE')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('debit_balance')
                ->label('DEBIT')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('credit_balance')
                ->label('CREDIT')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('net_movement')
                ->label('NET MOVEMENT')
                ->toggleable()
                ->alignment(Alignment::Right),
            Column::make('ending_balance')
                ->label('ENDING BALANCE')
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
        return $this->reportService->buildAccountBalanceReport($this->getFormattedStartDate(), $this->getFormattedEndDate(), $columns);
    }

    protected function getTransformer(ReportDTO $reportDTO): ExportableReport
    {
        return new AccountBalanceReportTransformer($reportDTO);
    }

    public function exportCSV(): StreamedResponse
    {
        return $this->exportService->exportToCsv($this->company, $this->report, $this->getFilterState('startDate'), $this->getFilterState('endDate'));
    }

    public function exportPDF(): StreamedResponse
    {
        return $this->exportService->exportToPdf($this->company, $this->report, $this->getFilterState('startDate'), $this->getFilterState('endDate'));
    }
}
