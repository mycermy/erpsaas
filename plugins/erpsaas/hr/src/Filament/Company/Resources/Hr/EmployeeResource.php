<?php

namespace Erpsaas\Hr\Filament\Company\Resources\Hr;

use Erpsaas\Core\Filament\Forms\Components\AddressFields;
use Erpsaas\Core\Filament\Forms\Components\CustomSection;
use Erpsaas\Core\Filament\Forms\Components\PhoneBuilder;
use Erpsaas\Core\Filament\Tables\Columns;
use Erpsaas\Hr\Filament\Company\Clusters\HumanResources;
use Erpsaas\Hr\Filament\Company\Resources\Hr\EmployeeResource\Pages;
use Erpsaas\Hr\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
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
                                    ->default(static fn() => Employee::getNextEmployeeNumber())
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
                        CustomSection::make('Contact Details')
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
                                    ->deletable(fn(PhoneBuilder $builder) => $builder->getItemsCount() > 1)
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
                            ->visible(fn(callable $get) => $get('separate_work_address'))
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
