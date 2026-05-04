<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SalaryRevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'salaryRevisions';

    protected static ?string $recordTitleAttribute = 'base_salary_amount';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('base_salary_amount')
                    ->label('Base Salary Amount')
                    ->numeric()
                    ->prefix('RM')
                    ->minValue(0)
                    ->step(0.01)
                    ->required(),
                Forms\Components\DatePicker::make('effective_from')
                    ->label('Effective From')
                    ->required()
                    ->default(now()),
                Forms\Components\Textarea::make('reason')
                    ->label('Reason for Change')
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Additional Notes')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('base_salary_amount')
            ->columns([
                Tables\Columns\TextColumn::make('base_salary_amount')
                    ->label('Base Salary')
                    ->money('MYR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('effective_from')
                    ->label('Effective From')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['company_id'] = $this->getOwnerRecord()->company_id;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
