<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Resources;

use Erpsaas\Core\Enums\Accounting\DocumentDiscountMethod;
use Erpsaas\Core\Enums\Accounting\DocumentType;
use Erpsaas\Core\Enums\Setting\Font;
use Erpsaas\Core\Enums\Setting\PaymentTerms;
use Erpsaas\Core\Enums\Setting\Template;
use Erpsaas\Core\Filament\Company\Clusters\Settings;
use Erpsaas\Core\Filament\Company\Clusters\Settings\Resources\DocumentDefaultResource\Pages;
use Erpsaas\Core\Filament\Forms\Components\DocumentPreview;
use Erpsaas\Core\Models\Setting\DocumentDefault;
use Filament\Forms;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DocumentDefaultResource extends Resource
{
    protected static ?string $model = DocumentDefault::class;

    protected static ?string $cluster = Settings::class;

    protected static ?string $modelLabel = 'document template';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->live()
            ->schema([
                self::getGeneralSection(),
                self::getContentSection(),
                self::getTemplateSection(),
                self::getBillColumnLabelsSection(),
            ]);
    }

    public static function getGeneralSection(): Component
    {
        return \Filament\Schemas\Components\Section::make('General')
            ->schema([
                Forms\Components\TextInput::make('number_prefix')
                    ->label(translate('Number prefix'))
                    ->nullable(),
                Forms\Components\Select::make('payment_terms')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Payment terms'))
                    ->options(PaymentTerms::class),
                Forms\Components\Select::make('discount_method')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Discount method'))
                    ->options(DocumentDiscountMethod::class),
            ])->columns();
    }

    public static function getContentSection(): Component
    {
        return \Filament\Schemas\Components\Section::make('Content')
            ->hidden(static fn(DocumentDefault $record) => $record->type === DocumentType::Bill)
            ->schema([
                Forms\Components\TextInput::make('header')
                    ->label(translate('Header'))
                    ->nullable(),
                Forms\Components\TextInput::make('subheader')
                    ->label(translate('Subheader'))
                    ->nullable(),
                Forms\Components\Textarea::make('terms')
                    ->label(translate('Terms'))
                    ->nullable(),
                Forms\Components\Textarea::make('footer')
                    ->label(translate('Footer'))
                    ->nullable(),
            ])->columns();
    }

    public static function getTemplateSection(): Component
    {
        return \Filament\Schemas\Components\Section::make('Template')
            ->description('Choose the template and edit the column names.')
            ->hidden(static fn(DocumentDefault $record) => $record->type === DocumentType::Bill)
            ->schema([
                \Filament\Schemas\Components\Grid::make(1)
                    ->schema([
                        Forms\Components\FileUpload::make('logo')
                            ->maxSize(1024)
                            ->label(translate('Logo'))
                            ->openable()
                            ->directory('logos/document')
                            ->image()
                            ->imageCropAspectRatio('3:2')
                            ->panelAspectRatio('3:2')
                            ->panelLayout('compact')
                            ->extraAttributes([
                                'class' => 'es-file-upload document-logo-preview',
                            ])
                            ->loadingIndicatorPosition('left')
                            ->removeUploadedFileButtonPosition('right'),
                        Forms\Components\Checkbox::make('show_logo')
                            ->label(translate('Show logo'))
                            ->hidden(is_demo_environment()),
                        Forms\Components\ColorPicker::make('accent_color')
                            ->label(translate('Accent color')),
                        Forms\Components\Select::make('font')
                            ->required()
                            ->markAsRequired(false)
                            ->label(translate('Font'))
                            ->allowHtml()
                            ->options(
                                collect(Font::cases())
                                    ->mapWithKeys(static fn($case) => [
                                        $case->value => "<span style='font-family:{$case->getLabel()}'>{$case->getLabel()}</span>",
                                    ]),
                            ),
                        Forms\Components\Select::make('template')
                            ->required()
                            ->markAsRequired(false)
                            ->label(translate('Template'))
                            ->options(Template::class),
                        ...static::getColumnLabelsSchema(),
                    ])->columnSpan(1),
                DocumentPreview::make()
                    ->template(static fn(Get $get) => Template::parse($get('template')))
                    ->preview()
                    ->columnSpan([
                        'lg' => 2,
                    ]),
            ])->columns(3);
    }

    public static function getBillColumnLabelsSection(): Component
    {
        return \Filament\Schemas\Components\Section::make('Column Labels')
            ->visible(static fn(DocumentDefault $record) => $record->type === DocumentType::Bill)
            ->schema(static::getColumnLabelsSchema())->columns();
    }

    public static function getColumnLabelsSchema(): array
    {
        return [
            Forms\Components\Select::make('item_name.option')
                ->required()
                ->markAsRequired(false)
                ->label(translate('Item name'))
                ->options(DocumentDefault::getAvailableItemNameOptions())
                ->afterStateUpdated(static function (Get $get, Set $set, $state, $old) {
                    if ($state !== 'other' && $old === 'other' && filled($get('item_name.custom'))) {
                        $set('item_name.old_custom', $get('item_name.custom'));
                        $set('item_name.custom', null);
                    }

                    if ($state === 'other' && $old !== 'other') {
                        $set('item_name.custom', $get('item_name.old_custom'));
                    }
                }),
            Forms\Components\TextInput::make('item_name.custom')
                ->label(' ')
                ->extraFieldWrapperAttributes(static fn(DocumentDefault $record) => [
                    'class' => $record->type === DocumentType::Bill ? 'report-hidden-label' : '',
                ])
                ->disabled(static fn(callable $get) => $get('item_name.option') !== 'other')
                ->nullable(),
            Forms\Components\Select::make('unit_name.option')
                ->required()
                ->markAsRequired(false)
                ->label(translate('Unit name'))
                ->options(DocumentDefault::getAvailableUnitNameOptions())
                ->afterStateUpdated(static function (Get $get, Set $set, $state, $old) {
                    if ($state !== 'other' && $old === 'other' && filled($get('unit_name.custom'))) {
                        $set('unit_name.old_custom', $get('unit_name.custom'));
                        $set('unit_name.custom', null);
                    }

                    if ($state === 'other' && $old !== 'other') {
                        $set('unit_name.custom', $get('unit_name.old_custom'));
                    }
                }),
            Forms\Components\TextInput::make('unit_name.custom')
                ->label(' ')
                ->extraFieldWrapperAttributes(static fn(DocumentDefault $record) => [
                    'class' => $record->type === DocumentType::Bill ? 'report-hidden-label' : '',
                ])
                ->disabled(static fn(callable $get) => $get('unit_name.option') !== 'other')
                ->nullable(),
            Forms\Components\Select::make('price_name.option')
                ->required()
                ->markAsRequired(false)
                ->label(translate('Price name'))
                ->options(DocumentDefault::getAvailablePriceNameOptions())
                ->afterStateUpdated(static function (Get $get, Set $set, $state, $old) {
                    if ($state !== 'other' && $old === 'other' && filled($get('price_name.custom'))) {
                        $set('price_name.old_custom', $get('price_name.custom'));
                        $set('price_name.custom', null);
                    }

                    if ($state === 'other' && $old !== 'other') {
                        $set('price_name.custom', $get('price_name.old_custom'));
                    }
                }),
            Forms\Components\TextInput::make('price_name.custom')
                ->label(' ')
                ->extraFieldWrapperAttributes(static fn(DocumentDefault $record) => [
                    'class' => $record->type === DocumentType::Bill ? 'report-hidden-label' : '',
                ])
                ->disabled(static fn(callable $get) => $get('price_name.option') !== 'other')
                ->nullable(),
            Forms\Components\Select::make('amount_name.option')
                ->required()
                ->markAsRequired(false)
                ->label(translate('Amount name'))
                ->options(DocumentDefault::getAvailableAmountNameOptions())
                ->afterStateUpdated(static function (Get $get, Set $set, $state, $old) {
                    if ($state !== 'other' && $old === 'other' && filled($get('amount_name.custom'))) {
                        $set('amount_name.old_custom', $get('amount_name.custom'));
                        $set('amount_name.custom', null);
                    }

                    if ($state === 'other' && $old !== 'other') {
                        $set('amount_name.custom', $get('amount_name.old_custom'));
                    }
                }),
            Forms\Components\TextInput::make('amount_name.custom')
                ->label(' ')
                ->extraFieldWrapperAttributes(static fn(DocumentDefault $record) => [
                    'class' => $record->type === DocumentType::Bill ? 'report-hidden-label' : '',
                ])
                ->disabled(static fn(callable $get) => $get('amount_name.option') !== 'other')
                ->nullable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('number_prefix'),
                Tables\Columns\TextColumn::make('template')
                    ->badge(),
                Tables\Columns\IconColumn::make('show_logo')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListDocumentDefaults::route('/'),
            'edit' => Pages\EditDocumentDefault::route('/{record}/edit'),
        ];
    }
}
