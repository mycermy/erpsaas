<?php

namespace Erpsaas\Core\Filament\Company\Clusters\Settings\Pages;

use Erpsaas\Core\Enums\Setting\DateFormat;
use Erpsaas\Core\Enums\Setting\NumberFormat;
use Erpsaas\Core\Enums\Setting\TimeFormat;
use Erpsaas\Core\Enums\Setting\WeekStart;
use Erpsaas\Core\Filament\Company\Clusters\Settings;
use Erpsaas\Core\Models\Setting\CompanyProfile as CompanyProfileModel;
use Erpsaas\Core\Models\Setting\Localization as LocalizationModel;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Utilities\Localization\Timezone;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

use function Filament\authorize;

/**
 * @property Form $form
 */
class Localization extends Page
{
    use InteractsWithFormActions;

    protected static ?string $title = 'Localization';

    protected string $view = 'filament.company.pages.setting.localization';

    protected static ?string $cluster = Settings::class;

    public ?array $data = [];

    #[Locked]
    public ?LocalizationModel $record = null;

    public function getTitle(): string | Htmlable
    {
        return translate(static::$title);
    }

    public static function getNavigationLabel(): string
    {
        return translate(static::$title);
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::ScreenTwoExtraLarge;
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->record = LocalizationModel::firstOrNew([
            'company_id' => $user?->current_company_id,
        ]);

        abort_unless(static::canView($this->record), 404);

        $this->fillForm();
    }

    public function fillForm(): void
    {
        $data = $this->record->attributesToArray();

        $this->form->fill($data);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            $this->handleRecordUpdate($this->record, $data);
        } catch (Halt $exception) {
            return;
        }

        $this->getSavedNotification()->send();
    }

    protected function getSavedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title(__('filament-panels::resources/pages/edit-record.notifications.saved.title'));
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                $this->getGeneralSection(),
                $this->getDateAndTimeSection(),
                $this->getFinancialAndFiscalSection(),
            ])
            ->model($this->record)
            ->statePath('data')
            ->operation('edit');
    }

    protected function getGeneralSection(): Component
    {
        return Section::make('General')
            ->schema([
                Select::make('language')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Language'))
                    ->options(LocalizationModel::getAllLanguages())
                    ->disabled(is_demo_environment())
                    ->searchable(),
                Select::make('timezone')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Timezone'))
                    ->options(Timezone::getTimezoneOptions(CompanyProfileModel::first()->address->country_code))
                    ->searchable(),
            ])->columns();
    }

    protected function getDateAndTimeSection(): Component
    {
        return Section::make('Date & Time')
            ->schema([
                Select::make('date_format')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Date format'))
                    ->options(DateFormat::class)
                    ->live(),
                Select::make('time_format')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Time format'))
                    ->options(TimeFormat::class),
                Select::make('week_start')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Week start'))
                    ->options(WeekStart::class),
            ])->columns();
    }

    protected function getFinancialAndFiscalSection(): Component
    {
        $beforeNumber = translate('Before number');
        $afterNumber = translate('After number');
        $selectPosition = translate('Select position');

        return Section::make('Financial & Fiscal')
            ->schema([
                Select::make('number_format')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Number format'))
                    ->options(NumberFormat::class),
                Select::make('percent_first')
                    ->required()
                    ->markAsRequired(false)
                    ->label(translate('Percent position'))
                    ->boolean($beforeNumber, $afterNumber, $selectPosition),
                Group::make()
                    ->schema([
                        Group::make()
                            ->schema([
                                Select::make('fiscal_year_end_month')
                                    ->required()
                                    ->markAsRequired(false)
                                    ->label(translate('Fiscal year end month'))
                                    ->options(array_combine(range(1, 12), array_map(static fn($month) => company_today()->month($month)->monthName, range(1, 12))))
                                    ->afterStateUpdated(static fn(Set $set) => $set('fiscal_year_end_day', null))
                                    ->columnSpan(2)
                                    ->live(),
                                Select::make('fiscal_year_end_day')
                                    ->placeholder('Day')
                                    ->required()
                                    ->markAsRequired(false)
                                    ->label(translate('Fiscal year end day'))
                                    ->columnSpan(1)
                                    ->options(function (Get $get) {
                                        $month = (int) $get('fiscal_year_end_month');

                                        $daysInMonth = company_today()->month($month)->daysInMonth;

                                        return array_combine(range(1, $daysInMonth), range(1, $daysInMonth));
                                    })
                                    ->live(),
                            ])
                            ->columns(3)
                            ->columnSpan(2),
                    ])->columns(3),
            ])->columns();
    }

    protected function handleRecordUpdate(LocalizationModel $record, array $data): LocalizationModel
    {
        $record->fill($data);

        $keysToWatch = [
            'language',
            'timezone',
            'date_format',
            'week_start',
            'time_format',
        ];

        $isDirty = $record->isDirty($keysToWatch);

        $record->save();

        if ($isDirty) {
            CompanySettingsService::invalidateSettings($record->company_id);
            $this->dispatch('localizationUpdated');
        }

        return $record;
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    public static function canView(Model $record): bool
    {
        try {
            return authorize('update', $record)->allowed();
        } catch (AuthorizationException $exception) {
            return $exception->toResponse()->allowed();
        }
    }
}
