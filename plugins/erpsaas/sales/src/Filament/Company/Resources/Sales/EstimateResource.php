<?php

namespace Erpsaas\Sales\Filament\Company\Resources\Sales;

use Erpsaas\Sales\Filament\Company\Clusters\Sales;
use Erpsaas\Core\Enums\Accounting\AdjustmentCategory;
use Erpsaas\Core\Enums\Accounting\AdjustmentStatus;
use Erpsaas\Core\Enums\Accounting\AdjustmentType;
use Erpsaas\Core\Enums\Accounting\DocumentDiscountMethod;
use Erpsaas\Core\Enums\Accounting\DocumentType;
use Erpsaas\Core\Enums\Accounting\EstimateStatus;
use Erpsaas\Core\Enums\Setting\PaymentTerms;
use Erpsaas\Sales\Filament\Company\Resources\Sales\ClientResource\RelationManagers\EstimatesRelationManager;
use Erpsaas\Sales\Filament\Company\Resources\Sales\EstimateResource\Pages;
use Erpsaas\Sales\Filament\Company\Resources\Sales\EstimateResource\Widgets;
use Erpsaas\Core\Filament\Exports\Accounting\EstimateExporter;
use Erpsaas\Core\Filament\Forms\Components\CreateAdjustmentSelect;
use Erpsaas\Core\Filament\Forms\Components\CreateClientSelect;
use Erpsaas\Core\Filament\Forms\Components\CreateCurrencySelect;
use Erpsaas\Core\Filament\Forms\Components\CreateOfferingSelect;
use Erpsaas\Core\Filament\Forms\Components\CustomTableRepeater;
use Erpsaas\Core\Filament\Forms\Components\DocumentFooterSection;
use Erpsaas\Core\Filament\Forms\Components\DocumentHeaderSection;
use Erpsaas\Core\Filament\Forms\Components\DocumentTotals;
use Erpsaas\Core\Filament\Tables\Actions\ReplicateBulkAction;
use Erpsaas\Core\Filament\Tables\Columns;
use Erpsaas\Core\Filament\Tables\Filters\DateRangeFilter;
use Erpsaas\Accounts\Models\Accounting\Adjustment;
use Erpsaas\Accounts\Models\Accounting\DocumentLineItem;
use Erpsaas\Accounts\Models\Accounting\Estimate;
use Erpsaas\Core\Models\Common\Client;
use Erpsaas\Core\Models\Common\Offering;
use Erpsaas\Core\Utilities\Currency\CurrencyAccessor;
use Erpsaas\Core\Utilities\Currency\CurrencyConverter;
use Erpsaas\Core\Utilities\RateCalculator;
use Awcodes\TableRepeater\Header;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\FusedGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class EstimateResource extends Resource
{
    protected static ?string $model = Estimate::class;

    protected static ?string $cluster = Sales::class;

    public static function form(Schema $form): Schema
    {
        $company = Auth::user()->currentCompany;

        $settings = $company->defaultEstimate;

        return $form
            ->schema([
                DocumentHeaderSection::make('Estimate Header')
                    ->defaultHeader($settings->header)
                    ->defaultSubheader($settings->subheader),
                \Filament\Schemas\Components\Section::make('Estimate Details')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(['md' => 2])->schema([
                            \Filament\Schemas\Components\Group::make([
                                CreateClientSelect::make('client_id')
                                    ->label('Client')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $currencyCode = Client::find($state)?->currency_code;

                                        if ($currencyCode) {
                                            $set('currency_code', $currencyCode);
                                        }
                                    }),
                                CreateCurrencySelect::make('currency_code'),
                            ]),
                            \Filament\Schemas\Components\Group::make([
                                Forms\Components\TextInput::make('estimate_number')
                                    ->label('Estimate number')
                                    ->default(static fn() => Estimate::getNextDocumentNumber()),
                                Forms\Components\TextInput::make('reference_number')
                                    ->label('Reference number'),
                                FusedGroup::make([
                                    Forms\Components\DatePicker::make('date')
                                        ->label('Estimate date')
                                        ->live()
                                        ->default(company_today()->toDateString())
                                        ->columnSpan(2)
                                        ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, $state) {
                                            $date = Carbon::parse($state)->toDateString();
                                            $expirationDate = Carbon::parse($get('expiration_date'))->toDateString();

                                            if ($date && $expirationDate && $date > $expirationDate) {
                                                $set('expiration_date', $date);
                                            }

                                            $paymentTerms = $get('payment_terms');
                                            if ($date && $paymentTerms && $paymentTerms !== 'custom') {
                                                $terms = PaymentTerms::parse($paymentTerms);
                                                $set('expiration_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
                                            }
                                        }),
                                    Forms\Components\Select::make('payment_terms')
                                        ->label('Payment terms')
                                        ->options(function () {
                                            return collect(PaymentTerms::cases())
                                                ->mapWithKeys(function (PaymentTerms $paymentTerm) {
                                                    return [$paymentTerm->value => $paymentTerm->getLabel()];
                                                })
                                                ->put('custom', 'Custom')
                                                ->toArray();
                                        })
                                        ->selectablePlaceholder(false)
                                        ->default($settings->payment_terms->value)
                                        ->live()
                                        ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, $state) {
                                            if (! $state || $state === 'custom') {
                                                return;
                                            }

                                            $date = $get('date');
                                            if ($date) {
                                                $terms = PaymentTerms::parse($state);
                                                $set('expiration_date', Carbon::parse($date)->addDays($terms->getDays())->toDateString());
                                            }
                                        }),
                                ])
                                    ->label('Estimate date')
                                    ->columns(3),
                                Forms\Components\DatePicker::make('expiration_date')
                                    ->label('Expiration date')
                                    ->default(function () use ($settings) {
                                        return company_today()->addDays($settings->payment_terms->getDays())->toDateString();
                                    })
                                    ->minDate(static function (\Filament\Schemas\Components\Utilities\Get $get) {
                                        return Carbon::parse($get('date'))->toDateString() ?? company_today()->toDateString();
                                    })
                                    ->live()
                                    ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, $state) {
                                        if (! $state) {
                                            return;
                                        }

                                        $date = $get('date');
                                        $paymentTerms = $get('payment_terms');

                                        if (! $date || $paymentTerms === 'custom') {
                                            return;
                                        }

                                        $term = PaymentTerms::parse($paymentTerms);
                                        $expected = Carbon::parse($date)->addDays($term->getDays());

                                        if (! Carbon::parse($state)->isSameDay($expected)) {
                                            $set('payment_terms', 'custom');
                                        }
                                    }),
                                Forms\Components\Select::make('discount_method')
                                    ->label('Discount method')
                                    ->options(DocumentDiscountMethod::class)
                                    ->softRequired()
                                    ->default($settings->discount_method)
                                    ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                        $discountMethod = DocumentDiscountMethod::parse($state);

                                        if ($discountMethod->isPerDocument()) {
                                            $set('lineItems.*.salesDiscounts', []);
                                        }
                                    })
                                    ->live(),
                            ])->grow(true),
                        ]),
                        CustomTableRepeater::make('lineItems')
                            ->hiddenLabel()
                            ->relationship()
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(true)
                            ->reorderable()
                            ->orderColumn('line_number')
                            ->reorderAtStart()
                            ->cloneable()
                            ->addActionLabel('Add an item')
                            ->headers(function (\Filament\Schemas\Components\Utilities\Get $get) use ($settings) {
                                $hasDiscounts = DocumentDiscountMethod::parse($get('discount_method'))->isPerLineItem();

                                $headers = [
                                    Header::make($settings->resolveColumnLabel('item_name', 'Items'))
                                        ->width('30%'),
                                    Header::make($settings->resolveColumnLabel('unit_name', 'Quantity'))
                                        ->width('10%'),
                                    Header::make($settings->resolveColumnLabel('price_name', 'Price'))
                                        ->width('10%'),
                                ];

                                if ($hasDiscounts) {
                                    $headers[] = Header::make('Adjustments')->width('30%');
                                } else {
                                    $headers[] = Header::make('Taxes')->width('30%');
                                }

                                $headers[] = Header::make($settings->resolveColumnLabel('amount_name', 'Amount'))
                                    ->width('10%')
                                    ->alignment('right');

                                return $headers;
                            })
                            ->schema([
                                \Filament\Schemas\Components\Group::make([
                                    CreateOfferingSelect::make('offering_id')
                                        ->label('Item')
                                        ->hiddenLabel()
                                        ->placeholder('Select item')
                                        ->required()
                                        ->live()
                                        ->inlineSuffix()
                                        ->sellable()
                                        ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get, $state, ?DocumentLineItem $record) {
                                            $offeringId = $state;
                                            $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));
                                            $isPerLineItem = $discountMethod->isPerLineItem();

                                            $existingTaxIds = [];
                                            $existingDiscountIds = [];

                                            if ($record) {
                                                $existingTaxIds = $record->salesTaxes()->pluck('adjustments.id')->toArray();
                                                if ($isPerLineItem) {
                                                    $existingDiscountIds = $record->salesDiscounts()->pluck('adjustments.id')->toArray();
                                                }
                                            }

                                            $with = [
                                                'salesTaxes' => static function ($query) use ($existingTaxIds) {
                                                    $query->where(static function ($query) use ($existingTaxIds) {
                                                        $query->where('status', AdjustmentStatus::Active)
                                                            ->orWhereIn('adjustments.id', $existingTaxIds);
                                                    });
                                                },
                                            ];

                                            if ($isPerLineItem) {
                                                $with['salesDiscounts'] = static function ($query) use ($existingDiscountIds) {
                                                    $query->where(static function ($query) use ($existingDiscountIds) {
                                                        $query->where('status', AdjustmentStatus::Active)
                                                            ->orWhereIn('adjustments.id', $existingDiscountIds);
                                                    });
                                                };
                                            }

                                            $offeringRecord = Offering::with($with)->find($offeringId);

                                            if (! $offeringRecord) {
                                                return;
                                            }

                                            $unitPrice = CurrencyConverter::convertCentsToFormatSimple($offeringRecord->price, 'USD');

                                            $set('description', $offeringRecord->description);
                                            $set('unit_price', $unitPrice);
                                            $set('salesTaxes', $offeringRecord->salesTaxes->pluck('id')->toArray());

                                            if ($isPerLineItem) {
                                                $set('salesDiscounts', $offeringRecord->salesDiscounts->pluck('id')->toArray());
                                            }
                                        }),
                                    Forms\Components\TextInput::make('description')
                                        ->placeholder('Enter item description')
                                        ->hiddenLabel(),
                                ])->columnSpan(1),
                                Forms\Components\TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->live()
                                    ->maxValue(9999999999.99)
                                    ->default(1),
                                Forms\Components\TextInput::make('unit_price')
                                    ->hiddenLabel()
                                    ->money(useAffix: false)
                                    ->live()
                                    ->default(0),
                                \Filament\Schemas\Components\Group::make([
                                    CreateAdjustmentSelect::make('salesTaxes')
                                        ->label('Taxes')
                                        ->hiddenLabel()
                                        ->placeholder('Select taxes')
                                        ->category(AdjustmentCategory::Tax)
                                        ->type(AdjustmentType::Sales)
                                        ->adjustmentsRelationship('salesTaxes')
                                        ->saveRelationshipsUsing(null)
                                        ->dehydrated(true)
                                        ->inlineSuffix()
                                        ->preload()
                                        ->multiple()
                                        ->live()
                                        ->searchable(),
                                    CreateAdjustmentSelect::make('salesDiscounts')
                                        ->label('Discounts')
                                        ->hiddenLabel()
                                        ->placeholder('Select discounts')
                                        ->category(AdjustmentCategory::Discount)
                                        ->type(AdjustmentType::Sales)
                                        ->adjustmentsRelationship('salesDiscounts')
                                        ->saveRelationshipsUsing(null)
                                        ->dehydrated(true)
                                        ->inlineSuffix()
                                        ->multiple()
                                        ->live()
                                        ->hidden(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                            $discountMethod = DocumentDiscountMethod::parse($get('../../discount_method'));

                                            return $discountMethod->isPerDocument();
                                        })
                                        ->searchable(),
                                ])->columnSpan(1),
                                Forms\Components\Placeholder::make('total')
                                    ->hiddenLabel()
                                    ->extraAttributes(['class' => 'text-left sm:text-right'])
                                    ->content(function (\Filament\Schemas\Components\Utilities\Get $get) {
                                        $quantity = max((float) ($get('quantity') ?? 0), 0);
                                        $unitPrice = CurrencyConverter::isValidAmount($get('unit_price'), 'USD')
                                            ? CurrencyConverter::convertToFloat($get('unit_price'), 'USD')
                                            : 0;
                                        $salesTaxes = $get('salesTaxes') ?? [];
                                        $salesDiscounts = $get('salesDiscounts') ?? [];
                                        $currencyCode = $get('../../currency_code') ?? CurrencyAccessor::getDefaultCurrency();

                                        $subtotal = $quantity * $unitPrice;

                                        $subtotalInCents = CurrencyConverter::convertToCents($subtotal, $currencyCode);

                                        $taxAmountInCents = Adjustment::whereIn('id', $salesTaxes)
                                            ->get()
                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                if ($adjustment->computation->isPercentage()) {
                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                } else {
                                                    return $adjustment->getRawOriginal('rate');
                                                }
                                            });

                                        $discountAmountInCents = Adjustment::whereIn('id', $salesDiscounts)
                                            ->get()
                                            ->sum(function (Adjustment $adjustment) use ($subtotalInCents) {
                                                if ($adjustment->computation->isPercentage()) {
                                                    return RateCalculator::calculatePercentage($subtotalInCents, $adjustment->getRawOriginal('rate'));
                                                } else {
                                                    return $adjustment->getRawOriginal('rate');
                                                }
                                            });

                                        // Final total
                                        $totalInCents = $subtotalInCents + ($taxAmountInCents - $discountAmountInCents);

                                        return CurrencyConverter::formatCentsToMoney($totalInCents, $currencyCode);
                                    }),
                            ]),
                        DocumentTotals::make()
                            ->type(DocumentType::Estimate),
                        Forms\Components\Textarea::make('terms')
                            ->default($settings->terms)
                            ->columnSpanFull(),
                    ]),
                DocumentFooterSection::make('Estimate Footer')
                    ->defaultFooter($settings->footer),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expiration_date')
            ->columns([
                Columns::id(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('expiration_date')
                    ->label('Expiration date')
                    ->asRelativeDay()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimate_number')
                    ->label('Number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('client.name')
                    ->sortable()
                    ->searchable()
                    ->hiddenOn(EstimatesRelationManager::class),
                Tables\Columns\TextColumn::make('total')
                    ->currencyWithConversion(static fn(Estimate $record) => $record->currency_code)
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->hiddenOn(EstimatesRelationManager::class),
                Tables\Filters\SelectFilter::make('status')
                    ->options(EstimateStatus::class)
                    ->native(false),
                DateRangeFilter::make('date')
                    ->fromLabel('From date')
                    ->untilLabel('To date')
                    ->indicatorLabel('Date'),
                DateRangeFilter::make('expiration_date')
                    ->fromLabel('From expiration date')
                    ->untilLabel('To expiration date')
                    ->indicatorLabel('Due'),
            ])
            ->headerActions([
                \Filament\Actions\ExportAction::make()
                    ->exporter(EstimateExporter::class),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\ActionGroup::make([
                        \Filament\Actions\EditAction::make()
                            ->url(static fn(Estimate $record) => Pages\EditEstimate::getUrl(['record' => $record])),
                        \Filament\Actions\ViewAction::make()
                            ->url(static fn(Estimate $record) => Pages\ViewEstimate::getUrl(['record' => $record])),
                        Estimate::getReplicateAction(\Filament\Actions\ReplicateAction::class),
                        Estimate::getApproveDraftAction(\Filament\Actions\Action::class),
                        Estimate::getMarkAsSentAction(\Filament\Actions\Action::class),
                        Estimate::getMarkAsAcceptedAction(\Filament\Actions\Action::class),
                        Estimate::getMarkAsDeclinedAction(\Filament\Actions\Action::class),
                        Estimate::getConvertToInvoiceAction(\Filament\Actions\Action::class),
                    ])->dropdown(false),
                    \Filament\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    ReplicateBulkAction::make()
                        ->label('Replicate')
                        ->modalWidth(Width::Large)
                        ->modalDescription('Replicating estimates will also replicate their line items. Are you sure you want to proceed?')
                        ->successNotificationTitle('Estimates replicated successfully')
                        ->failureNotificationTitle('Failed to replicate estimates')
                        ->databaseTransaction()
                        ->deselectRecordsAfterCompletion()
                        ->excludeAttributes([
                            'estimate_number',
                            'date',
                            'expiration_date',
                            'approved_at',
                            'accepted_at',
                            'converted_at',
                            'declined_at',
                            'last_sent_at',
                            'last_viewed_at',
                            'status',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ])
                        ->beforeReplicaSaved(function (Estimate $replica) {
                            $replica->status = EstimateStatus::Draft;
                            $replica->estimate_number = Estimate::getNextDocumentNumber();
                            $replica->date = company_today();
                            $replica->expiration_date = company_today()->addDays($replica->company->defaultInvoice->payment_terms->getDays());
                        })
                        ->withReplicatedRelationships(['lineItems'])
                        ->withExcludedRelationshipAttributes('lineItems', [
                            'subtotal',
                            'total',
                            'created_by',
                            'updated_by',
                            'created_at',
                            'updated_at',
                        ]),
                    \Filament\Actions\BulkAction::make('approveDrafts')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates approved')
                        ->failureNotificationTitle('Failed to approve estimates')
                        ->before(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn(Estimate $record) => ! $record->canBeApproved());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Approval failed')
                                    ->body('Only draft estimates can be approved. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->approveDraft();
                            });

                            $action->success();
                        }),
                    \Filament\Actions\BulkAction::make('markAsSent')
                        ->label('Mark as sent')
                        ->icon('heroicon-o-paper-airplane')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates sent')
                        ->failureNotificationTitle('Failed to mark estimates as sent')
                        ->before(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn(Estimate $record) => ! $record->canBeMarkedAsSent());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Sending failed')
                                    ->body('Only unsent estimates can be marked as sent. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsSent();
                            });

                            $action->success();
                        }),
                    \Filament\Actions\BulkAction::make('markAsAccepted')
                        ->label('Mark as accepted')
                        ->icon('heroicon-o-check-badge')
                        ->databaseTransaction()
                        ->successNotificationTitle('Estimates accepted')
                        ->failureNotificationTitle('Failed to mark estimates as accepted')
                        ->before(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn(Estimate $record) => ! $record->canBeMarkedAsAccepted());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Acceptance failed')
                                    ->body('Only sent estimates that haven\'t been accepted can be marked as accepted. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsAccepted();
                            });

                            $action->success();
                        }),
                    \Filament\Actions\BulkAction::make('markAsDeclined')
                        ->label('Mark as declined')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->databaseTransaction()
                        ->color('danger')
                        ->modalHeading('Mark Estimates as Declined')
                        ->modalDescription('Are you sure you want to mark the selected estimates as declined? This action cannot be undone.')
                        ->successNotificationTitle('Estimates declined')
                        ->failureNotificationTitle('Failed to mark estimates as declined')
                        ->before(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $isInvalid = $records->contains(fn(Estimate $record) => ! $record->canBeMarkedAsDeclined());

                            if ($isInvalid) {
                                Notification::make()
                                    ->title('Declination failed')
                                    ->body('Only sent estimates that haven\'t been declined can be marked as declined. Please adjust your selection and try again.')
                                    ->persistent()
                                    ->danger()
                                    ->send();

                                $action->cancel(true);
                            }
                        })
                        ->action(function (Collection $records, \Filament\Actions\BulkAction $action) {
                            $records->each(function (Estimate $record) {
                                $record->markAsDeclined();
                            });

                            $action->success();
                        }),
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
            'index' => Pages\ListEstimates::route('/'),
            'create' => Pages\CreateEstimate::route('/create'),
            'view' => Pages\ViewEstimate::route('/{record}'),
            'edit' => Pages\EditEstimate::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\EstimateOverview::class,
        ];
    }
}
