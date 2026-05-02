<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr;

use Erpsaas\Hr\Enums\Hr\SalaryPartBasis;
use Erpsaas\Hr\Enums\Hr\SalaryPartType;
use Erpsaas\Hr\Filament\Company\Resources\Hr\SalaryPartResource\Pages;
use Erpsaas\Hr\Models\SalaryPart;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalaryPartResource extends Resource
{
    protected static ?string $model = SalaryPart::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Human Resources';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('part_number')
                            ->required()
                            ->disabledOn('edit')
                            ->prefix(fn (callable $get) => gettype($get('type')) == 'object' ? $get('type')->getPrefix() : SalaryPartType::tryFrom($get('type'))->getPrefix())
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull()
                            ->label('Description'),
                    ])
                    ->columns(),
                Forms\Components\Section::make('Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('in_net_salary')
                            ->label('Include in Net Salary')
                            ->default(true)
                            ->live()
                            ->disabled(fn ($record, $get) => $get('type') === SalaryPartType::EmployerCost)
                            ->disabledOn('edit')
                            ->columnSpanFull()
                            ->helperText('If enabled, this salary part will be included in the calculation of the net salary.'),
                        Forms\Components\Select::make('type')
                            ->localizeLabel()
                            ->options(SalaryPartType::class)
                            ->default(SalaryPartType::BaseSalary)
                            ->live()
                            ->disabledOn('edit')
                            ->afterStateUpdated(function (Set $set, SalaryPartType $state) {
                                if ($state === SalaryPartType::BaseSalary) {
                                    $set('basis', SalaryPartBasis::Fixed);
                                } elseif ($state === SalaryPartType::EmployerCost) {
                                    $set('in_net_salary', false);
                                }
                                $set('part_number', SalaryPart::getNextSalaryPartNumber($state));
                            })
                            ->afterStateHydrated(function ($record, Set $set, $state) {
                                if ($record) {
                                    return;
                                }
                                $type = gettype($state) == 'object' ? $state : SalaryPartType::tryFrom($state);
                                $set('part_number', SalaryPart::getNextSalaryPartNumber($type ?? SalaryPartType::BaseSalary));
                            })
                            ->required(),
                        Forms\Components\Select::make('basis')
                            ->localizeLabel()
                            ->options(SalaryPartBasis::class)
                            ->default(SalaryPartBasis::Fixed)
                            ->live()
                            ->disabled(function ($record, $get) {
                                if ($record || $get('type') === SalaryPartType::BaseSalary) {
                                    return true;
                                }

                                return false;
                            })
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->suffix(fn (callable $get) => $get('basis') === SalaryPartBasis::Fixed ? currency() : '%')
                            ->required()
                            ->default(0),
                        Forms\Components\Select::make('debit_account_id')
                            ->label('Debit Account')
                            ->relationship('debitAccount', 'name', function (Builder $query) {
                                $query->where('category', 'expense');
                            })
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('credit_account_id')
                            ->label('Credit Account')
                            ->relationship('creditAccount', 'name', function (Builder $query) {
                                $query->where('category', 'liability');
                            })
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('part_number')
                    ->label('Part Number')
                    ->prefix(fn (SalaryPart $record) => $record->type->getPrefix())
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('debitAccount.name')
                    ->label('Debit Account')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('creditAccount.name')
                    ->label('Credit Account')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('type')
                    ->label('Type')
                    ->icon(fn ($state): string => match ($state) {
                        SalaryPartType::BaseSalary => 'heroicon-o-currency-dollar',
                        SalaryPartType::Deduction => 'heroicon-o-minus-circle',
                        SalaryPartType::Addition => 'heroicon-o-plus-circle',
                        SalaryPartType::Reimbursement => 'heroicon-o-clipboard-document-check',
                        SalaryPartType::Bonus => 'heroicon-o-gift',
                        SalaryPartType::Commission => 'heroicon-o-briefcase',
                        SalaryPartType::Overtime => 'heroicon-o-clock',
                        SalaryPartType::EmployerCost => 'heroicon-o-building-office',
                        SalaryPartType::Other => 'heroicon-o-arrows-right-left',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label('Created')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->label('Last Modified')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListSalaryParts::route('/'),
            'create' => Pages\CreateSalaryPart::route('/create'),
            'edit' => Pages\EditSalaryPart::route('/{record}/edit'),
        ];
    }
}
