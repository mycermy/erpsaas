<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources;

use Erpsaas\Core\Filament\Company\Clusters\Settings;
use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource\Pages;
use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource\RelationManagers\ChildrenRelationManager;
use Erpsaas\Core\Models\Core\Department;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;

    protected static ?string $modelLabel = 'Department';

    protected static ?string $cluster = Settings::class;

    protected static ?string $slug = 'settings/departments';

    public static function getModelLabel(): string
    {
        $modelLabel = static::$modelLabel;

        return translate($modelLabel);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->autofocus()
                            ->required()
                            ->label(translate('Name'))
                            ->maxLength(100),
                        Forms\Components\Select::make('manager_id')
                            ->relationship(
                                name: 'manager',
                                titleAttribute: 'name',
                                modifyQueryUsing: static function (Builder $query) {
                                    $company = Auth::user()?->currentCompany;

                                    if (! $company) {
                                        return $query->whereRaw('1 = 0');
                                    }

                                    $companyUsers = $company->allUsers()->pluck('id')->toArray();

                                    return $query->whereIn('id', $companyUsers);
                                }
                            )
                            ->label(translate('Manager'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        \Filament\Schemas\Components\Group::make()
                            ->schema([
                                Forms\Components\Select::make('parent_id')
                                    ->label(translate('Parent department'))
                                    ->relationship('parent', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->nullable(),
                                Forms\Components\Textarea::make('description')
                                    ->autosize()
                                    ->nullable()
                                    ->label(translate('Description')),
                            ])->columns(1),
                    ])->columns(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->localizeLabel()
                    ->weight('semibold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manager.name')
                    ->localizeLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('children_count')
                    ->localizeLabel('Children')
                    ->badge()
                    ->counts('children')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
