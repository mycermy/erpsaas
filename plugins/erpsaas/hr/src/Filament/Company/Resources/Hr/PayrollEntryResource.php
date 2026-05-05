<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr;

use Barryvdh\Snappy\Facades\SnappyPdf;
use Erpsaas\Hr\Filament\Company\Clusters\HumanResources;
use Erpsaas\Hr\Filament\Company\Resources\Hr\PayrollEntryResource\Pages;
use Erpsaas\Hr\Models\EmployeeAdvance;
use Erpsaas\Hr\Models\PayrollEntry;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PayrollEntryResource extends Resource
{
    protected static ?string $model = PayrollEntry::class;

    protected static ?string $cluster = HumanResources::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('entry_number')
                            ->required()
                            ->default(fn () => PayrollEntry::getNextPayrollEntryNumber())
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('from_date')
                            ->live()
                            ->default(fn () => now()->day > 10 ? now()->addMonth()->startOfMonth() : now()->startOfMonth())
                            ->afterStateUpdated(function (callable $set, $state) {
                                $toDate = \Carbon\Carbon::parse($state)->endOfMonth()->toDateString();
                                $set('to_date', $toDate);
                            })
                            ->required(),
                        Forms\Components\DatePicker::make('to_date')
                            ->default(fn () => now()->day > 10 ? now()->addMonth()->endOfMonth() : now()->endOfMonth())
                            ->required(),
                    ])->columns(),
                Forms\Components\Section::make('Employee Details')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->contact->first_name} {$record->contact->last_name} ({$record->employee_number})")
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('advance_ids', []))
                            ->required(),
                        Forms\Components\Select::make('salary_structure_id')
                            ->label('Salary Structure')
                            ->relationship('salaryStructure', 'name')
                            ->required(),
                    ])->columns(),
                Forms\Components\Section::make('Advance Recovery')
                    ->schema([
                        Forms\Components\CheckboxList::make('advance_ids')
                            ->label('Recover Outstanding Advances')
                            ->helperText('Select any outstanding advances to deduct from this payroll payment.')
                            ->options(function (Forms\Get $get): array {
                                $employeeId = $get('employee_id');

                                if (! $employeeId) {
                                    return [];
                                }

                                return EmployeeAdvance::query()
                                    ->where('employee_id', $employeeId)
                                    ->whereNull('recovered_at')
                                    ->whereNotNull('given_at')
                                    ->get()
                                    ->mapWithKeys(fn (EmployeeAdvance $advance) => [
                                        $advance->id => sprintf(
                                            '%s — %s (given %s)',
                                            number_format((float) $advance->amount, 2),
                                            ucfirst($advance->reason ?? 'advance'),
                                            $advance->given_at->format('M j, Y')
                                        ),
                                    ])
                                    ->all();
                            })
                            ->columns(1)
                            ->dehydrated(),
                    ])
                    ->hidden(function (?PayrollEntry $record, Forms\Get $get): bool {
                        if ($record !== null) {
                            return true;
                        }

                        return ! filled($get('employee_id'));
                    }),
                Forms\Components\Section::make('Recovered Advances')
                    ->schema([
                        Forms\Components\Placeholder::make('advances_recovered')
                            ->label('Advances recovered in this payroll')
                            ->content(function (?PayrollEntry $record): HtmlString {
                                if (! $record) {
                                    return new HtmlString('—');
                                }

                                $advances = $record->advances;

                                if ($advances->isEmpty()) {
                                    return new HtmlString('None');
                                }

                                $lines = $advances->map(fn (EmployeeAdvance $advance) => sprintf(
                                    '%s — %s (recovered %s)',
                                    number_format((float) $advance->amount, 2),
                                    ucfirst($advance->reason ?? 'advance'),
                                    $advance->recovered_at?->format('M j, Y') ?? '?'
                                ))->join('<br>');

                                return new HtmlString($lines);
                            }),
                    ])
                    ->hidden(fn (?PayrollEntry $record): bool => ! $record || $record->advances->isEmpty()),
                Forms\Components\Section::make('Bill Information')
                    ->schema([
                        Forms\Components\Placeholder::make('bill.bill_number')
                            ->label('Bill Number')
                            ->content(fn (?PayrollEntry $record) => $record?->bill?->bill_number ?? '—'),
                        Forms\Components\Placeholder::make('bill.status')
                            ->label('Bill Status')
                            ->content(fn (?PayrollEntry $record) => $record?->bill?->status ? ucfirst($record->bill->status->value) : '—'),
                        Forms\Components\Placeholder::make('bill.total')
                            ->label('Bill Amount')
                            ->content(fn (?PayrollEntry $record) => $record?->bill?->total ? money($record->bill->total, $record->bill->currency_code) : '—'),
                        Forms\Components\Placeholder::make('bill.paid_at')
                            ->label('Paid Date')
                            ->content(fn (?PayrollEntry $record) => $record?->bill?->paid_at?->format('M d, Y h:i A') ?? '—'),
                    ])->columns()
                    ->hidden(fn (?PayrollEntry $record) => ! $record || $record->bill_id === null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entry_number')
                    ->label('Entry Number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.employee_number')
                    ->label('Employee')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('salaryStructure.name')
                    ->label('Salary Structure')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('from_date')
                    ->label('From')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_date')
                    ->label('To')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bill.bill_number')
                    ->label('Bill #')
                    ->sortable()
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('bill.status')
                    ->label('Bill Status')
                    ->colors([
                        'gray' => 'null',
                        'warning' => 'open',
                        'success' => 'paid',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state->value) : 'No Bill'),
            ])
            ->defaultSort(function (Builder $query): Builder {
                return $query
                    ->orderBy('from_date', 'desc')
                    ->orderBy('entry_number', 'desc');
            })
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view_bill')
                        ->label('View Bill')
                        ->icon('heroicon-o-document-text')
                        ->visible(fn (PayrollEntry $record) => $record->bill_id !== null)
                        ->action(function (PayrollEntry $record) {
                            // Navigate to bill edit page using the URL path
                            return redirect(route('filament.company.purchases.resources.purchases.bills.edit', [
                                'tenant' => Filament::getTenant(),
                                'record' => $record->bill_id,
                            ]));
                        }),
                    Tables\Actions\Action::make('preview_payslip')
                        ->label('Preview Payslip')
                        ->icon('heroicon-o-eye')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Close')
                        ->modalWidth('5xl')
                        ->modalHeading(fn (PayrollEntry $record) => 'Payslip ' . $record->entry_number)
                        ->modalContent(function (PayrollEntry $record): View {
                            return view('erpsaas-hr::filament.company.resources.hr.payroll-entry-resource.modals.payslip-preview', [
                                'record' => $record,
                                'payslip' => $record->getPayslipBreakdown(),
                            ]);
                        }),
                    Tables\Actions\Action::make('export_payslip_pdf')
                        ->label('Export PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function (PayrollEntry $record) {
                            $record->loadMissing('employee.contact', 'salaryStructure');

                            $payslip = $record->getPayslipBreakdown();

                            $employeeName = trim((string) ($record->employee?->contact?->first_name . ' ' . $record->employee?->contact?->last_name));
                            $employeeSlug = $employeeName !== '' ? Str::slug($employeeName) : 'employee';

                            $filename = sprintf(
                                'payslip-%s-%s-%s.pdf',
                                $record->entry_number,
                                $employeeSlug,
                                now()->format('Y-m-d')
                            );

                            $pdf = SnappyPdf::loadView('erpsaas-hr::filament.company.resources.hr.payroll-entry-resource.pdf.payslip', [
                                'record' => $record,
                                'payslip' => $payslip,
                            ]);

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->inline();
                            }, $filename);
                        }),
                    Tables\Actions\EditAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['bill', 'employee', 'salaryStructure']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollEntries::route('/'),
            'create' => Pages\CreatePayrollEntry::route('/create'),
            'edit' => Pages\EditPayrollEntry::route('/{record}/edit'),
        ];
    }
}
