<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr;

use Barryvdh\Snappy\Facades\SnappyPdf;
use Erpsaas\Hr\Filament\Company\Resources\Hr\PayrollEntryResource\Pages;
use Erpsaas\Hr\Models\PayrollEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PayrollEntryResource extends Resource
{
    protected static ?string $model = PayrollEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Human Resources';

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
                            ->required(),
                        Forms\Components\Select::make('salary_structure_id')
                            ->label('Salary Structure')
                            ->relationship('salaryStructure', 'name')
                            ->required(),
                    ])->columns(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollEntries::route('/'),
            'create' => Pages\CreatePayrollEntry::route('/create'),
            'edit' => Pages\EditPayrollEntry::route('/{record}/edit'),
        ];
    }
}
