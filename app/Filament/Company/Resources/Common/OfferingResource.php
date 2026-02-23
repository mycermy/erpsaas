<?php

namespace App\Filament\Company\Resources\Common;

use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Common\OfferingType;
use App\Filament\Company\Resources\Common\OfferingResource\Pages;
use App\Filament\Forms\Components\Banner;
use App\Filament\Forms\Components\CreateAccountSelect;
use App\Filament\Forms\Components\CreateAdjustmentSelect;
use App\Models\Common\Offering;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use JaOcero\RadioDeck\Forms\Components\RadioDeck;
use Zrm\Inventory\Enums\TrackMethod;

class OfferingResource extends Resource
{
    protected static ?string $model = Offering::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Banner::make('inactiveAdjustments')
                    ->label('Inactive adjustments')
                    ->warning()
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn(?Offering $record) => $record?->hasInactiveAdjustments())
                    ->columnSpanFull()
                    ->description(function (Offering $record) {
                        $inactiveAdjustments = collect();

                        foreach ($record->adjustments as $adjustment) {
                            if ($adjustment->isInactive() && $inactiveAdjustments->doesntContain($adjustment->name)) {
                                $inactiveAdjustments->push($adjustment->name);
                            }
                        }

                        $adjustmentsList = $inactiveAdjustments->map(static function ($name) {
                            return "<span class='font-medium'>{$name}</span>";
                        })->join(', ');

                        $output = "<p class='text-sm'>This offering contains inactive adjustments that need to be addressed: {$adjustmentsList}</p>";

                        return new HtmlString($output);
                    }),
                static::getGeneralSection(),
                // Stockable Section
                static::getStockableSection(),
                // Sellable Section
                static::getSellableSection(),
                // Purchasable Section
                static::getPurchasableSection(),
            ])->columns();
    }

    public static function getGeneralSection(bool $hasAttributeChoices = true): Forms\Components\Section
    {
        return Forms\Components\Section::make('General')
            ->schema([
                RadioDeck::make('type')
                    ->options(OfferingType::class)
                    ->default(OfferingType::Product)
                    ->icons(OfferingType::class)
                    ->color('primary')
                    ->columns()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->autofocus()
                    ->required()
                    ->columnStart(1)
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->money(),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->columnSpan(2)
                    ->rows(3),
                Forms\Components\CheckboxList::make('attributes')
                    ->options([
                        'Sellable' => 'Sellable',
                        'Purchasable' => 'Purchasable',
                        'Stockable' => 'Stockable',
                    ])
                    ->visible($hasAttributeChoices)
                    ->hiddenLabel()
                    ->required()
                    ->live()
                    ->bulkToggleable()
                    ->validationMessages([
                        'required' => 'The offering must be either sellable, purchasable, or stockable.',
                    ]),
            ])->columns();
    }

    public static function getStockableSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Stock Information')
            ->description('Configure inventory tracking settings for this product')
            ->schema([
                Forms\Components\TextInput::make('inventoryItem.sku')
                    ->label('SKU')
                    ->maxLength(255)
                    ->helperText('Stock Keeping Unit - unique identifier for this item')
                    ->afterStateHydrated(function (Forms\Components\TextInput $component, ?Offering $record) {
                        if ($record && $record->inventoryItem) {
                            $component->state($record->inventoryItem->sku);
                        }
                    }),

                Forms\Components\Select::make('inventoryItem.track_method')
                    ->label('Cost Tracking Method')
                    ->options(TrackMethod::class)
                    ->default(TrackMethod::FIFO)
                    ->helperText('Method used to calculate cost of goods sold')
                    ->required(fn(Forms\Get $get) => in_array('Stockable', $get('attributes') ?? []))
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?Offering $record) {
                        if ($record && $record->inventoryItem) {
                            $component->state($record->inventoryItem->track_method);
                        }
                    }),

                Forms\Components\Toggle::make('inventoryItem.track_batches')
                    ->label('Track Batches/Lots')
                    ->default(true)
                    ->helperText('Enable batch/lot tracking for detailed cost tracking')
                    ->afterStateHydrated(function (Forms\Components\Toggle $component, ?Offering $record) {
                        if ($record && $record->inventoryItem) {
                            $component->state($record->inventoryItem->track_batches);
                        }
                    }),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\TextInput::make('inventoryItem.reorder_level')
                            ->label('Reorder Level')
                            ->numeric()
                            ->default(0)
                            ->step(1)
                            ->minValue(0)
                            ->helperText('Alert when stock falls below this level')
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, ?Offering $record) {
                                if ($record && $record->inventoryItem) {
                                    $component->state($record->inventoryItem->reorder_level);
                                }
                            }),

                        Forms\Components\TextInput::make('inventoryItem.reorder_quantity')
                            ->label('Reorder Quantity')
                            ->numeric()
                            ->default(0)
                            ->step(1)
                            ->minValue(0)
                            ->helperText('Suggested quantity to order when restocking')
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, ?Offering $record) {
                                if ($record && $record->inventoryItem) {
                                    $component->state($record->inventoryItem->reorder_quantity);
                                }
                            }),
                    ])
                    ->columns(2),

                Forms\Components\Fieldset::make('Account Mapping')
                    ->schema([
                        CreateAccountSelect::make('inventoryItem.asset_account_id')
                            ->label('Inventory Asset Account')
                            ->category(AccountCategory::Asset)
                            ->type(AccountType::CurrentAsset)
                            ->helperText('Balance sheet account to track inventory value')
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?Offering $record) {
                                if ($record && $record->inventoryItem) {
                                    $component->state($record->inventoryItem->asset_account_id);
                                }
                            })
                            ->required()
                            ->validationMessages([
                                'required' => 'The asset account is required for stockable offerings.',
                            ]),

                        CreateAccountSelect::make('expense_account_id')
                            ->label('COGS Expense Account')
                            ->category(AccountCategory::Expense)
                            ->type(AccountType::OperatingExpense)
                            ->helperText('Expense statement account for cost of goods sold')
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?Offering $record) {
                                if ($record && $record->inventoryItem) {
                                    $component->state($record->expense_account_id);
                                }
                            })
                            ->required()
                            ->validationMessages([
                                'required' => 'The expense account is required for stockable offerings.',
                            ]),
                    ])
                    ->columns(2),
            ])
            ->columns()
            ->visible(static fn(Forms\Get $get) => in_array('Stockable', $get('attributes') ?? []));
    }

    public static function getSellableSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Sale Information')
            ->schema([
                CreateAccountSelect::make('income_account_id')
                    ->label('Income account')
                    ->category(AccountCategory::Revenue)
                    ->type(AccountType::OperatingRevenue)
                    ->required()
                    ->validationMessages([
                        'required' => 'The income account is required for sellable offerings.',
                    ]),
                CreateAdjustmentSelect::make('salesTaxes')
                    ->label('Sales tax')
                    ->category(AdjustmentCategory::Tax)
                    ->type(AdjustmentType::Sales)
                    ->multiple(),
                CreateAdjustmentSelect::make('salesDiscounts')
                    ->label('Sales discount')
                    ->category(AdjustmentCategory::Discount)
                    ->type(AdjustmentType::Sales)
                    ->multiple(),
            ])
            ->columns()
            ->visible(static fn(Forms\Get $get) => in_array('Sellable', $get('attributes') ?? []));
    }

    public static function getPurchasableSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Purchase Information')
            ->schema([
                CreateAccountSelect::make('expense_account_id')
                    ->label('Expense account')
                    ->category(AccountCategory::Expense)
                    ->type(AccountType::OperatingExpense)
                    ->visible(static fn(Forms\Get $get) => ! in_array('Stockable', $get('attributes') ?? []))
                    ->required(
                        static fn(Forms\Get $get) => in_array('Purchasable', $get('attributes') ?? []) &&
                            ! in_array('Stockable', $get('attributes') ?? [])
                    )
                    ->required()
                    ->validationMessages([
                        'required' => 'The expense account is required for purchasable offerings.',
                    ]),
                Placeholder::make('inventory_note')
                    ->label('Inventory Asset Account')
                    ->content('Purchases will be recorded to the Inventory Asset Account configured in the Stock Information section above.')
                    ->visible(static fn(Forms\Get $get) => in_array('Stockable', $get('attributes') ?? [])),
                CreateAdjustmentSelect::make('purchaseTaxes')
                    ->label('Purchase tax')
                    ->category(AdjustmentCategory::Tax)
                    ->type(AdjustmentType::Purchase)
                    ->multiple(),
                CreateAdjustmentSelect::make('purchaseDiscounts')
                    ->label('Purchase discount')
                    ->category(AdjustmentCategory::Discount)
                    ->type(AdjustmentType::Purchase)
                    ->multiple(),
            ])
            ->columns()
            ->visible(static fn(Forms\Get $get) => in_array('Purchasable', $get('attributes') ?? []));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->selectRaw("
                        *,
                        CONCAT_WS(' & ',
                            CASE WHEN sellable THEN 'Sellable' END,
                            CASE WHEN purchasable THEN 'Purchasable' END,
	                        CASE WHEN stockable THEN 'Stockable' END
                        ) AS attributes
                    ");
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name'),
                Tables\Columns\TextColumn::make('inventoryItem.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('attributes')
                    ->label('Attributes')
                    ->badge(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->currency()
                    ->sortable()
                    ->description(function (Offering $record) {
                        $adjustments = $record->adjustments()
                            ->pluck('name')
                            ->join(', ');

                        if (empty($adjustments)) {
                            return null;
                        }

                        $adjustmentsList = Str::of($adjustments)->limit(40);

                        return "+ {$adjustmentsList}";
                    }),
                Tables\Columns\TextColumn::make('inventoryItem.track_method')
                    ->label('Track Method')
                    ->badge()
                    ->toggleable()
                    ->placeholder('—'),
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
            'index' => Pages\ListOfferings::route('/'),
            'create' => Pages\CreateOffering::route('/create'),
            'edit' => Pages\EditOffering::route('/{record}/edit'),
        ];
    }
}
