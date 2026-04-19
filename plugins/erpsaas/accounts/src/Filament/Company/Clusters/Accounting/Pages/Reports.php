<?php

namespace Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages;

use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\AccountBalances;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\AccountsPayableAging;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\AccountsReceivableAging;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\AccountTransactions;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\BalanceSheet;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\CashFlowStatement;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\ClientBalanceSummary;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\ClientPaymentPerformance;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\IncomeStatement;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\TrialBalance;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\VendorBalanceSummary;
use Erpsaas\Accounts\Filament\Company\Clusters\Accounting\Pages\Reports\VendorPaymentPerformance;
use Erpsaas\Core\Filament\Infolists\Components\ReportEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Support\Colors\Color;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.company.pages.reports';

    public static function getNavigationLabel(): string
    {
        return __('Reports');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Accounting');
    }

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn(): bool => request()->routeIs([
                    static::getRouteName(),
                    static::getRouteName() . '.*',
                ]))
                ->sort(static::getNavigationSort())
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->url(static::getNavigationUrl()),
        ];
    }

    public function reportsInfolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->state([])
            ->schema([
                Section::make('Financial Statements')
                    ->aside()
                    ->description('Key financial statements that provide an overview of your company’s financial health and performance.')
                    ->extraAttributes(['class' => 'es-report-card'])
                    ->schema([
                        ReportEntry::make('income_statement')
                            ->hiddenLabel()
                            ->heading('Income Statement')
                            ->description('Shows revenue, expenses, and net earnings over a period, indicating overall financial performance.')
                            ->icon('heroicon-o-chart-bar')
                            ->iconColor(Color::Purple)
                            ->url(IncomeStatement::getUrl()),
                        ReportEntry::make('balance_sheet')
                            ->hiddenLabel()
                            ->heading('Balance Sheet')
                            ->description('Displays your company’s assets, liabilities, and equity at a single point in time, showing overall financial health and stability.')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->iconColor(Color::Teal)
                            ->url(BalanceSheet::getUrl()),
                        ReportEntry::make('cash_flow_statement')
                            ->hiddenLabel()
                            ->heading('Cash Flow Statement')
                            ->description('Tracks cash inflows and outflows, giving insight into liquidity and cash management over a period.')
                            ->icon('heroicon-o-document-currency-dollar')
                            ->iconColor(Color::Cyan)
                            ->url(CashFlowStatement::getUrl()),
                    ]),
                Section::make('Client Reports')
                    ->aside()
                    ->description('Reports that provide detailed information on your company’s client transactions and balances.')
                    ->extraAttributes(['class' => 'es-report-card'])
                    ->schema([
                        ReportEntry::make('ar_aging')
                            ->hiddenLabel()
                            ->heading('Accounts Receivable Aging')
                            ->description('Lists outstanding receivables by client, showing how long invoices have been unpaid.')
                            ->icon('heroicon-o-calendar-date-range')
                            ->iconColor(Color::Indigo)
                            ->url(AccountsReceivableAging::getUrl()),
                        ReportEntry::make('client_balance_summary')
                            ->hiddenLabel()
                            ->heading('Client Balance Summary')
                            ->description('Shows total invoiced amounts, payments received, and outstanding balances for each client, helping identify top clients and opportunities for growth.')
                            ->icon('heroicon-o-receipt-percent')
                            ->iconColor(Color::Emerald)
                            ->url(ClientBalanceSummary::getUrl()),
                        ReportEntry::make('client_payment_performance')
                            ->hiddenLabel()
                            ->heading('Client Payment Performance')
                            ->description('Analyzes payment behavior showing average days to pay, on-time payment rates, and late payment patterns for each client.')
                            ->icon('heroicon-o-clock')
                            ->iconColor(Color::Fuchsia)
                            ->url(ClientPaymentPerformance::getUrl()),
                    ]),
                Section::make('Vendor Reports')
                    ->aside()
                    ->description('Reports that provide detailed information on your company’s vendor transactions and balances.')
                    ->extraAttributes(['class' => 'es-report-card'])
                    ->schema([
                        ReportEntry::make('ap_aging')
                            ->hiddenLabel()
                            ->heading('Accounts Payable Aging')
                            ->description('Lists outstanding payables by vendor, showing how long invoices have been unpaid.')
                            ->icon('heroicon-o-clock')
                            ->iconColor(Color::Rose)
                            ->url(AccountsPayableAging::getUrl()),
                        ReportEntry::make('vendor_balance_summary')
                            ->hiddenLabel()
                            ->heading('Vendor Balance Summary')
                            ->description('Shows total billed amounts, payments made, and outstanding balances for each vendor, helping track payment obligations and vendor relationships.')
                            ->icon('heroicon-o-banknotes')
                            ->iconColor(Color::Orange)
                            ->url(VendorBalanceSummary::getUrl()),
                        ReportEntry::make('vendor_payment_performance')
                            ->hiddenLabel()
                            ->heading('Vendor Payment Performance')
                            ->description('Analyzes payment behavior showing average days to pay, on-time payment rates, and late payment patterns for each vendor.')
                            ->icon('heroicon-o-clock')
                            ->iconColor(Color::Violet)
                            ->url(VendorPaymentPerformance::getUrl()),
                    ]),
                Section::make('Detailed Reports')
                    ->aside()
                    ->description('Detailed reports that provide a comprehensive view of your company’s financial transactions and account balances.')
                    ->extraAttributes(['class' => 'es-report-card'])
                    ->schema([
                        ReportEntry::make('account_balances')
                            ->hiddenLabel()
                            ->heading('Account Balances')
                            ->description('Lists all accounts and their balances, including starting, debit, credit, net movement, and ending balances.')
                            ->icon('heroicon-o-calculator')
                            ->iconColor(Color::Slate)
                            ->url(AccountBalances::getUrl()),
                        ReportEntry::make('trial_balance')
                            ->hiddenLabel()
                            ->heading('Trial Balance')
                            ->description('Summarizes all account debits and credits on a specific date to verify the ledger is balanced.')
                            ->icon('heroicon-o-scale')
                            ->iconColor(Color::Sky)
                            ->url(TrialBalance::getUrl()),
                        ReportEntry::make('account_transactions')
                            ->hiddenLabel()
                            ->heading('Account Transactions')
                            ->description('A record of all transactions, essential for monitoring and reconciling financial activity in the ledger.')
                            ->icon('heroicon-o-list-bullet')
                            ->iconColor(Color::Yellow)
                            ->url(AccountTransactions::getUrl()),
                    ]),
            ]);
    }
}
