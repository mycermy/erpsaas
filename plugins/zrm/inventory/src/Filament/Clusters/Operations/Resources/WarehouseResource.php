<?php

namespace Zrm\Inventory\Filament\Clusters\Operations\Resources;

use Zrm\Inventory\Filament\Clusters\Operations\Resources\WarehouseResource\Pages;
use Zrm\Inventory\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Zrm\Inventory\Filament\Clusters\Operations;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $panel = 'company';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $cluster = Operations::class;

    // protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 2;

    // protected static ?string $slug = 'inventory/warehouses';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Warehouse Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('code')
                            ->maxLength(50)
                            ->helperText('Unique warehouse code for identification'),

                        Forms\Components\Toggle::make('active')
                            ->default(true)
                            ->helperText('Inactive warehouses cannot be used in transactions'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Location Details')
                    ->schema([
                        Forms\Components\Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('postal_code')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('country')
                            ->maxLength(100),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Forms\Components\Section::make('Contact Information')
                    ->schema([
                        Forms\Components\TextInput::make('contact_name')
                            ->label('Contact Name')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->where('company_id', filament()->getTenant()->id))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('state')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\TextColumn::make('total_items')
                    ->label('Items')
                    ->getStateUsing(fn(Warehouse $record) => $record->stockLevels()->count())
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_stock')
                    ->label('Total Stock')
                    ->getStateUsing(fn(Warehouse $record) => $record->stockLevels()->sum('quantity_on_hand'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                Tables\Columns\IconColumn::make('active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')
                    ->default(true),
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
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
