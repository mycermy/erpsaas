<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr;

use Erpsaas\Core\Filament\Forms\Components\AddressFields;
use Erpsaas\Core\Filament\Forms\Components\CustomSection;
use Erpsaas\Core\Filament\Forms\Components\PhoneBuilder;
use Erpsaas\Core\Filament\Tables\Columns;
use Erpsaas\Hr\Filament\Company\Clusters\HumanResources;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource\Pages;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource\RelationManagers;
use Erpsaas\Hr\Models\Employee;
use Erpsaas\Hr\Models\EmployeeAdvance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $cluster = HumanResources::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('employee_number')
                                    ->maxLength(255)
                                    ->default(static fn () => Employee::getNextEmployeeNumber())
                                    ->required()
                                    ->columnStart(1),
                                Forms\Components\TextInput::make('job_title')
                                    ->maxLength(255)
                                    ->required()
                                    ->columnStart(2),
                                Forms\Components\TextInput::make('department')
                                    ->maxLength(255)
                                    ->columnStart(1),
                            ]),
                    ])->columns(1),
                Forms\Components\Section::make('Personal Information (PDPA Protected)')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('nric')
                                    ->label('NRIC (MyKad)')
                                    ->maxLength(255)
                                    ->helperText('Encrypted - Malaysian IC number')
                                    ->columnStart(1),
                                Forms\Components\TextInput::make('passport_number')
                                    ->label('Passport Number')
                                    ->maxLength(255)
                                    ->helperText('Encrypted - For foreign workers')
                                    ->columnStart(2),
                            ]),
                    ])->columns(1)
                    ->description('Sensitive personal identification data protected under Malaysia PDPA'),
                Forms\Components\Section::make('Bank Details (Encrypted)')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('bank_account_number')
                                    ->label('Bank Account Number')
                                    ->maxLength(255)
                                    ->helperText('Encrypted')
                                    ->columnStart(1),
                                Forms\Components\TextInput::make('bank_name')
                                    ->label('Bank Name')
                                    ->maxLength(255)
                                    ->columnStart(2),
                                Forms\Components\TextInput::make('bank_branch')
                                    ->label('Bank Branch')
                                    ->maxLength(255)
                                    ->columnStart(1),
                            ]),
                    ])->columns(1),
                Forms\Components\Section::make('Malaysian Social Security (Encrypted)')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('epf_number')
                                    ->label('EPF Number (KWSP)')
                                    ->maxLength(255)
                                    ->helperText('Encrypted - Employees Provident Fund')
                                    ->columnStart(1),
                                Forms\Components\TextInput::make('socso_number')
                                    ->label('SOCSO Number (PERKESO)')
                                    ->maxLength(255)
                                    ->helperText('Encrypted - Social Security Organisation')
                                    ->columnStart(2),
                                Forms\Components\TextInput::make('income_tax_number')
                                    ->label('Income Tax Number (LHDN)')
                                    ->maxLength(255)
                                    ->helperText('Encrypted - Inland Revenue Board')
                                    ->columnStart(1),
                            ]),
                    ])->columns(1),
                Forms\Components\Section::make('Emergency Contact (Encrypted)')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\TextInput::make('emergency_contact_name')
                                    ->label('Contact Name')
                                    ->maxLength(255)
                                    ->columnStart(1),
                                Forms\Components\TextInput::make('emergency_contact_phone')
                                    ->label('Contact Phone')
                                    ->tel()
                                    ->maxLength(255)
                                    ->helperText('Encrypted')
                                    ->columnStart(2),
                                Forms\Components\TextInput::make('emergency_contact_relationship')
                                    ->label('Relationship')
                                    ->maxLength(255)
                                    ->placeholder('e.g. Spouse, Parent, Sibling')
                                    ->columnStart(1),
                            ]),
                    ])->columns(1),
                Forms\Components\Section::make('Salary Information')
                    ->schema([
                        Forms\Components\Group::make()
                            ->columns()
                            ->schema([
                                Forms\Components\Placeholder::make('latest_salary.base_salary_amount')
                                    ->label('Current Base Salary')
                                    ->content(function (?Employee $record) {
                                        if (! $record) {
                                            return '—';
                                        }
                                        $latestRevision = $record->salaryRevisions()
                                            ->orderBy('effective_from', 'desc')
                                            ->first();

                                        return $latestRevision
                                            ? 'RM ' . number_format($latestRevision->base_salary_amount, 2)
                                            : '—';
                                    })
                                    ->columnStart(1)
                                    ->helperText('To update salary, use the "Salary Revisions" tab below'),
                                Forms\Components\Placeholder::make('latest_salary.effective_from')
                                    ->label('Effective From')
                                    ->content(function (?Employee $record) {
                                        if (! $record) {
                                            return '—';
                                        }
                                        $latestRevision = $record->salaryRevisions()
                                            ->orderBy('effective_from', 'desc')
                                            ->first();

                                        return $latestRevision?->effective_from?->format('M d, Y') ?? '—';
                                    })
                                    ->columnStart(2),
                            ]),
                    ])->columns(1)
                    ->description('View current salary information. Scroll down to the Salary Revisions tab to manage salary history.'),
                Forms\Components\Section::make('Contact Details')
                    ->schema([
                        CustomSection::make('Contact')
                            ->relationship('contact')
                            ->saveRelationshipsUsing(null)
                            ->saveRelationshipsBeforeChildrenUsing(null)
                            ->dehydrated(true)
                            ->contained(false)
                            ->schema([
                                Forms\Components\Hidden::make('is_primary')
                                    ->default(true),
                                Forms\Components\TextInput::make('first_name')
                                    ->label('First name')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('last_name')
                                    ->label('Last name')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->columnSpanFull()
                                    ->maxLength(255),
                                PhoneBuilder::make('phones')
                                    ->hiddenLabel()
                                    ->blockLabels(false)
                                    ->default([
                                        ['type' => 'primary'],
                                    ])
                                    ->columnSpanFull()
                                    ->blocks([
                                        Forms\Components\Builder\Block::make('primary')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Phone')
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('mobile')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Mobile')
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('toll_free')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Toll free')
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                        Forms\Components\Builder\Block::make('fax')
                                            ->schema([
                                                Forms\Components\TextInput::make('number')
                                                    ->label('Fax')
                                                    ->live()
                                                    ->maxLength(15),
                                            ])->maxItems(1),
                                    ])
                                    ->deletable(fn (PhoneBuilder $builder) => $builder->getItemsCount() > 1)
                                    ->reorderable(false)
                                    ->blockNumbers(false)
                                    ->addActionLabel('Add Phone'),
                            ])->columns(),
                    ])->columns(1),
                Forms\Components\Section::make('Address Details')
                    ->schema([
                        CustomSection::make('Home Address')
                            ->relationship('homeAddress')
                            ->saveRelationshipsUsing(null)
                            ->saveRelationshipsBeforeChildrenUsing(null)
                            ->dehydrated(true)
                            ->contained(false)
                            ->schema([
                                Forms\Components\Hidden::make('type')
                                    ->default('home'),
                                AddressFields::make(),
                            ])->columns(),
                        Forms\Components\Checkbox::make('separate_work_address')
                            ->label('Separate work address')
                            ->reactive()
                            ->columnSpanFull(),
                        CustomSection::make('Work Address')
                            ->visible(fn (callable $get) => $get('separate_work_address'))
                            ->relationship('workAddress')
                            ->saveRelationshipsUsing(null)
                            ->saveRelationshipsBeforeChildrenUsing(null)
                            ->dehydrated(true)
                            ->contained(false)
                            ->schema([
                                Forms\Components\Hidden::make('type')
                                    ->default('work'),
                                AddressFields::make(),
                            ])->columns(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('employee_number')
                    ->label('Employee Number')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact.first_name')
                    ->label('First Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact.last_name')
                    ->label('Last Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('job_title')
                    ->label('Job Title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('department')
                    ->label('Department')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('masked_nric')
                    ->label('NRIC')
                    ->getStateUsing(fn (Employee $record) => $record->masked_nric ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('masked_bank_account')
                    ->label('Bank Account')
                    ->getStateUsing(fn (Employee $record) => $record->masked_bank_account ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('encrypted_base_salary')
                    ->label('Base Salary')
                    ->getStateUsing(function (Employee $record) {
                        $latestRevision = $record->salaryRevisions()
                            ->orderBy('effective_from', 'desc')
                            ->first();

                        return $latestRevision?->base_salary_amount;
                    })
                    ->money('MYR')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('encrypted_base_salary', $direction);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('salary_effective_from')
                    ->label('Salary Effective')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('recordAdvance')
                        ->label('Record Advance')
                        ->icon('heroicon-o-banknotes')
                        ->modalHeading(fn (Employee $record) => 'Record Advance — ' . trim($record->contact->first_name . ' ' . $record->contact->last_name))
                        ->modalWidth('lg')
                        ->form([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('amount')
                                        ->required()
                                        ->numeric()
                                        ->minValue(0.01)
                                        ->step(0.01)
                                        ->prefix('RM')
                                        ->label('Advance Amount'),
                                    Forms\Components\DateTimePicker::make('given_at')
                                        ->label('Date Given')
                                        ->default(now())
                                        ->required(),
                                    Forms\Components\TextInput::make('reason')
                                        ->maxLength(255)
                                        ->placeholder('e.g. emergency, medical'),
                                    Forms\Components\Textarea::make('notes')
                                        ->rows(3)
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->action(function (Employee $record, array $data): void {
                            EmployeeAdvance::create([
                                'employee_id' => $record->id,
                                'amount' => $data['amount'],
                                'given_at' => $data['given_at'],
                                'reason' => $data['reason'] ?? null,
                                'notes' => $data['notes'] ?? null,
                            ]);

                            Notification::make()
                                ->title('Advance recorded')
                                ->body('The advance has been recorded successfully.')
                                ->success()
                                ->send();
                        }),
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
            RelationManagers\SalaryRevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
