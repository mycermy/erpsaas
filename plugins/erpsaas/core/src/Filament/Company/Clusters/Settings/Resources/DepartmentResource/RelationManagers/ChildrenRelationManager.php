<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DepartmentResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->localizeLabel()
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('manager_id')
                    ->localizeLabel()
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
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\MarkdownEditor::make('description')->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(translate('Department'))
            ->inverseRelationship('parent')
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
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make(),
                \Filament\Actions\AssociateAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        $existingChildren = $this->getRelationship()->pluck('id')->toArray();

                        return $query->whereNotIn('id', $existingChildren)
                            ->whereNotNull('parent_id');
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
