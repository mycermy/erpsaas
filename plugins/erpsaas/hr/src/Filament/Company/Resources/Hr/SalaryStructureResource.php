<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr;

use Erpsaas\Hr\Enums\Hr\SalaryPartBasis;
use Erpsaas\Hr\Filament\Company\Clusters\HumanResources;
use Erpsaas\Hr\Filament\Company\Resources\Hr\SalaryStructureResource\Pages;
use Erpsaas\Hr\Models\SalaryPart;
use Erpsaas\Hr\Models\SalaryStructure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SalaryStructureResource extends Resource
{
    protected static ?string $model = SalaryStructure::class;

    protected static ?string $cluster = HumanResources::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->columnSpanFull()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull()
                            ->maxLength(65535),
                        Forms\Components\DatePicker::make('effective_date')
                            ->default(date('Y-m-d', strtotime('first day of next month')))
                            ->required(),
                        Forms\Components\DatePicker::make('termination_date'),
                        Forms\Components\Select::make('account_id')
                            ->label('Payroll Liabilities Account')
                            ->relationship('payrollLiabilitiesAccount', 'name', fn(Builder $query) => $query->where('category', 'liability'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->columnSpanFull()
                            ->required()
                            ->helperText('Select the account to track payroll liabilities.'),
                    ])->columns(),
                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Repeater::make('spss')
                            ->relationship('spss')
                            ->label('Salary Parts')
                            ->schema([
                                Forms\Components\Select::make('salary_part_id')
                                    ->label('Part')
                                    ->options(SalaryPart::all()->pluck('name', 'id'))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        $set('amount', SalaryPart::find($state)?->amount ?? 0);
                                        $set('group', SalaryPart::find($state)?->type->value ?? '');
                                    })
                                    ->searchable(),
                                Forms\Components\TextInput::make('amount')
                                    ->label('Amount')
                                    ->required()
                                    ->numeric()
                                    ->suffix(function ($get) {
                                        $part = SalaryPart::find($get('salary_part_id'));

                                        return $part && $part->basis === SalaryPartBasis::Fixed ? currency() : '%';
                                    })
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->default(0),
                                Forms\Components\Hidden::make('group'),
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->columns()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('effective_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('termination_date')->date()->sortable(),
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
            'index' => Pages\ListSalaryStructures::route('/'),
            'create' => Pages\CreateSalaryStructure::route('/create'),
            'edit' => Pages\EditSalaryStructure::route('/{record}/edit'),
        ];
    }
}
