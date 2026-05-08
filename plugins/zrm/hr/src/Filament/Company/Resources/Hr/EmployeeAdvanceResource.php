<?php

namespace Zrm\Hr\Filament\Company\Resources\Hr;

use Zrm\Hr\Filament\Company\Clusters\HumanResources;
use Zrm\Hr\Filament\Company\Resources\Hr\EmployeeAdvanceResource\Pages;
use Zrm\Hr\Models\EmployeeAdvance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeAdvanceResource extends Resource
{
    protected static ?string $model = EmployeeAdvance::class;

    protected static ?string $cluster = HumanResources::class;

    // protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    // public static function getNavigationLabel(): string
    // {
    //     return __('Employee Advances');
    // }

    // public static function getNavigationGroup(): ?string
    // {
    //     return __('Human Resources');
    // }

    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Advance Details')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship(
                                'employee',
                                'id',
                                fn(Builder $query) => $query->with('contact')
                            )
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->contact->first_name} {$record->contact->last_name} ({$record->employee_number})")
                            ->preload()
                            ->required()
                            ->disabled(fn(?EmployeeAdvance $record) => $record?->isRecovered()),
                        Forms\Components\TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('RM')
                            ->disabled(fn(?EmployeeAdvance $record) => $record?->isRecovered()),
                        Forms\Components\DateTimePicker::make('given_at')
                            ->label('Date Given')
                            ->nullable()
                            ->disabled(fn(?EmployeeAdvance $record) => $record?->isRecovered()),
                        Forms\Components\TextInput::make('reason')
                            ->maxLength(255)
                            ->placeholder('e.g. emergency, medical')
                            ->disabled(fn(?EmployeeAdvance $record) => $record?->isRecovered()),
                        Forms\Components\Textarea::make('notes')
                            ->columnSpanFull()
                            ->rows(3)
                            ->disabled(fn(?EmployeeAdvance $record) => $record?->isRecovered()),
                    ])->columns(),
                Forms\Components\Section::make('Recovery Information')
                    ->schema([
                        Forms\Components\Placeholder::make('recovered_at')
                            ->label('Recovered At')
                            ->content(fn(?EmployeeAdvance $record) => $record?->recovered_at?->format('M d, Y h:i A') ?? '—'),
                        Forms\Components\Placeholder::make('recovered_from_payroll_id')
                            ->label('Recovered From Payroll Entry')
                            ->content(fn(?EmployeeAdvance $record) => $record?->recoveredFromPayroll?->entry_number ?? '—'),
                    ])->columns()
                    ->hidden(fn(?EmployeeAdvance $record) => ! $record?->isRecovered()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.employee_number')
                    ->label('Employee #')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.contact.first_name')
                    ->label('Employee Name')
                    ->formatStateUsing(fn($state, EmployeeAdvance $record) => trim(($record->employee->contact->first_name ?? '') . ' ' . ($record->employee->contact->last_name ?? '')))
                    ->searchable(['employees.employee_number']),
                Tables\Columns\TextColumn::make('amount')
                    ->sortable()
                    ->formatStateUsing(fn($state) => 'RM ' . number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('given_at')
                    ->label('Date Given')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('reason')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn(EmployeeAdvance $record) => $record->isRecovered() ? 'Recovered' : 'Pending')
                    ->colors([
                        'success' => 'Recovered',
                        'warning' => 'Pending',
                    ]),
                Tables\Columns\TextColumn::make('recovered_at')
                    ->label('Recovered At')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->defaultSort(fn(Builder $query) => $query->orderBy('created_at', 'desc'))
            ->filters([
                Tables\Filters\Filter::make('pending')
                    ->label('Pending Only')
                    ->query(fn(Builder $query) => $query->whereNull('recovered_at'))
                    ->toggle(),
                Tables\Filters\Filter::make('recovered')
                    ->label('Recovered Only')
                    ->query(fn(Builder $query) => $query->whereNotNull('recovered_at'))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                        ->hidden(fn(EmployeeAdvance $record) => $record->isRecovered()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['employee.contact', 'recoveredFromPayroll']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeAdvances::route('/'),
            'create' => Pages\CreateEmployeeAdvance::route('/create'),
            'edit' => Pages\EditEmployeeAdvance::route('/{record}/edit'),
        ];
    }
}
