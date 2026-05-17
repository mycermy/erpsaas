<?php

namespace App\Providers\Filament;

use Erpsaas\Core\Actions\FilamentCompanies\AddCompanyEmployee;
use Erpsaas\Core\Actions\FilamentCompanies\CreateConnectedAccount;
use Erpsaas\Core\Actions\FilamentCompanies\CreateNewUser;
use Erpsaas\Core\Actions\FilamentCompanies\CreateUserFromProvider;
use Erpsaas\Core\Actions\FilamentCompanies\DeleteCompany;
use Erpsaas\Core\Actions\FilamentCompanies\DeleteUser;
use Erpsaas\Core\Actions\FilamentCompanies\HandleInvalidState;
use Erpsaas\Core\Actions\FilamentCompanies\InviteCompanyEmployee;
use Erpsaas\Core\Actions\FilamentCompanies\RemoveCompanyEmployee;
use Erpsaas\Core\Actions\FilamentCompanies\ResolveSocialiteUser;
use Erpsaas\Core\Actions\FilamentCompanies\SetUserPassword;
use Erpsaas\Core\Actions\FilamentCompanies\UpdateCompanyName;
use Erpsaas\Core\Actions\FilamentCompanies\UpdateConnectedAccount;
use Erpsaas\Core\Actions\FilamentCompanies\UpdateUserPassword;
use Erpsaas\Core\Actions\FilamentCompanies\UpdateUserProfileInformation;
use Erpsaas\Core\Filament\Company\Pages\CreateCompany;
use Erpsaas\Core\Filament\Company\Pages\ManageCompany;
use Erpsaas\Core\Filament\Components\PanelShiftDropdown;
use Erpsaas\Core\Filament\Pages\Auth\Login;
use Erpsaas\Core\Filament\User\Clusters\Account;
use Erpsaas\Core\Http\Middleware\ConfigureCurrentCompany;
use Erpsaas\Core\Livewire\UpdatePassword;
use Erpsaas\Core\Livewire\UpdateProfileInformation;
use Erpsaas\Core\Models\Company;
use Erpsaas\Core\Models\CompanyInvitation;
use Erpsaas\Core\Models\ConnectedAccount;
use Erpsaas\Core\Models\Employeeship;
use Erpsaas\Core\Models\User;
use Erpsaas\Core\Services\CompanySettingsService;
use Erpsaas\Core\Support\FilamentComponentConfigurator;
use Exception;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Wallo\FilamentCompanies\Actions\GenerateRedirectForProvider;
use Wallo\FilamentCompanies\Enums\Feature;
use Wallo\FilamentCompanies\Enums\Provider;
use Wallo\FilamentCompanies\FilamentCompanies;
use Wallo\FilamentCompanies\Pages\Auth\Register;

class CompanyPanelProvider extends PanelProvider
{
    /**
     * @throws Exception
     */
    public function panel(Panel $panel): Panel
    {
        $isDemoEnvironment = is_demo_environment();

        return $panel
            ->default()
            ->id('company')
            ->path('company')
            ->login(Login::class)
            ->when(! $isDemoEnvironment, function (Panel $panel) {
                return $panel
                    ->registration(Register::class)
                    ->passwordReset();
            })
            ->tenantMenu(false)
            ->plugins([
                FilamentCompanies::make()
                    ->userPanel('user')
                    ->switchCurrentCompany()
                    ->updateProfileInformation(component: UpdateProfileInformation::class)
                    ->updatePasswords(component: UpdatePassword::class)
                    ->setPasswords()
                    ->connectedAccounts()
                    ->manageBrowserSessions()
                    ->accountDeletion()
                    ->profilePhotos()
                    ->api()
                    ->companies(invitations: true)
                    ->autoAcceptInvitations()
                    ->termsAndPrivacyPolicy()
                    ->notifications()
                    ->modals()
                    ->socialite(
                        condition: ! $isDemoEnvironment,
                        providers: [Provider::Github],
                        features: [Feature::RememberSession, Feature::ProviderAvatars],
                    ),
                PanelShiftDropdown::make()
                    ->logoutItem()
                    ->companySettings()
                    ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                        return $builder
                            ->items(Account::getNavigationItems());
                    }),
            ])
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->topNavigation()
            ->maxContentWidth(MaxWidth::Full)
            // Define navigation groups - clusters will auto-populate them via getNavigationGroup()
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Dashboard')
                    ->icon('heroicon-o-home-modern'),
                NavigationGroup::make()
                    ->label('Bengkel')
                    ->icon('heroicon-o-shopping-cart'),
                NavigationGroup::make()
                    ->label('Sales')
                    ->icon('heroicon-o-shopping-cart'),
                NavigationGroup::make()
                    ->label('Purchases')
                    ->icon('heroicon-o-shopping-bag'),
                NavigationGroup::make()
                    ->label('Inventory')
                    ->icon('heroicon-o-cube'),
                NavigationGroup::make()
                    ->label('Human Resources')
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make()
                    ->label('Accounting')
                    ->icon('heroicon-o-calculator'),
                NavigationGroup::make()
                    ->label('Banking')
                    ->icon('heroicon-o-building-library'),
                NavigationGroup::make()
                    ->label('Settings')
                    ->icon('heroicon-o-squares-2x2'),
            ])
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications(isLazy: false)
            ->viteTheme('resources/css/filament/company/theme.css')
            ->brandLogo(static fn () => view('components.icons.logo'))
            ->tenant(Company::class)
            ->tenantProfile(ManageCompany::class)
            ->tenantRegistration(CreateCompany::class)
            ->pages([
                // Pages\Dashboard::class,
            ])
            ->authGuard('web')
            ->widgets([
                // Widgets\AccountWidget::class,
                // Widgets\FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->tenantMiddleware([
                ConfigureCurrentCompany::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure FilamentCompanies to use custom models
        FilamentCompanies::useUserModel(User::class);
        FilamentCompanies::useCompanyModel(Company::class);
        FilamentCompanies::useEmployeeshipModel(Employeeship::class);
        FilamentCompanies::useCompanyInvitationModel(CompanyInvitation::class);
        FilamentCompanies::useConnectedAccountModel(ConnectedAccount::class);

        $this->configurePermissions();
        $this->configureDefaults();

        FilamentCompanies::createUsersUsing(CreateNewUser::class);
        FilamentCompanies::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        FilamentCompanies::updateUserPasswordsUsing(UpdateUserPassword::class);

        FilamentCompanies::createCompaniesUsing(CreateCompany::class);
        FilamentCompanies::updateCompanyNamesUsing(UpdateCompanyName::class);
        FilamentCompanies::addCompanyEmployeesUsing(AddCompanyEmployee::class);
        FilamentCompanies::inviteCompanyEmployeesUsing(InviteCompanyEmployee::class);
        FilamentCompanies::removeCompanyEmployeesUsing(RemoveCompanyEmployee::class);
        FilamentCompanies::deleteCompaniesUsing(DeleteCompany::class);
        FilamentCompanies::deleteUsersUsing(DeleteUser::class);

        FilamentCompanies::resolvesSocialiteUsersUsing(ResolveSocialiteUser::class);
        FilamentCompanies::createUsersFromProviderUsing(CreateUserFromProvider::class);
        FilamentCompanies::createConnectedAccountsUsing(CreateConnectedAccount::class);
        FilamentCompanies::updateConnectedAccountsUsing(UpdateConnectedAccount::class);
        FilamentCompanies::setUserPasswordsUsing(SetUserPassword::class);
        FilamentCompanies::handlesInvalidStateUsing(HandleInvalidState::class);
        FilamentCompanies::generatesProvidersRedirectsUsing(GenerateRedirectForProvider::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        FilamentCompanies::defaultApiTokenPermissions(['read']);

        FilamentCompanies::role('admin', 'Administrator', [
            'create',
            'read',
            'update',
            'delete',
        ])->description('Administrator users can perform any action.');

        FilamentCompanies::role('editor', 'Editor', [
            'read',
            'create',
            'update',
        ])->description('Editor users have the ability to read, create, and update.');
    }

    /**
     * Configure the default settings for Filament.
     */
    protected function configureDefaults(): void
    {
        $this->configureSelect();

        Forms\Components\FileUpload::configureUsing(function (Forms\Components\FileUpload $component): void {
            $component
                ->hidden(is_demo_environment());
        });

        Actions\CreateAction::configureUsing(static fn (Actions\CreateAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Actions\EditAction::configureUsing(static fn (Actions\EditAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Actions\DeleteAction::configureUsing(static fn (Actions\DeleteAction $action) => FilamentComponentConfigurator::configureDeleteAction($action));
        Tables\Actions\EditAction::configureUsing(static fn (Tables\Actions\EditAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Tables\Actions\CreateAction::configureUsing(static fn (Tables\Actions\CreateAction $action) => FilamentComponentConfigurator::configureActionModals($action));
        Tables\Actions\DeleteAction::configureUsing(static fn (Tables\Actions\DeleteAction $action) => FilamentComponentConfigurator::configureDeleteAction($action));
        Tables\Actions\DeleteBulkAction::configureUsing(static fn (Tables\Actions\DeleteBulkAction $action) => FilamentComponentConfigurator::configureDeleteAction($action));

        Tables\Table::configureUsing(static function (Tables\Table $table): void {
            $table::$defaultDateDisplayFormat = CompanySettingsService::getDefaultDateFormat();
            $table::$defaultTimeDisplayFormat = CompanySettingsService::getDefaultTimeFormat();
            $table::$defaultDateTimeDisplayFormat = CompanySettingsService::getDefaultDateTimeFormat();

            $table
                ->paginationPageOptions([5, 10, 25, 50, 100])
                ->filtersFormWidth(MaxWidth::Small)
                ->filtersTriggerAction(
                    fn (Tables\Actions\Action $action) => $action
                        ->button()
                        ->label('Filters')
                        ->slideOver()
                );
        });

        Tables\Columns\TextColumn::configureUsing(function (Tables\Columns\TextColumn $column): void {
            $column->placeholder('–');
        });

        TextEntry::configureUsing(function (TextEntry $component): void {
            $component->placeholder('–');
        });

        Tables\Actions\ExportAction::configureUsing(function (Tables\Actions\ExportAction $action) {
            $action
                ->color('primary')
                ->slideOver();
        });
    }

    /**
     * Configure the default settings for the Select component.
     */
    protected function configureSelect(): void
    {
        Select::configureUsing(function (Select $select): void {
            $select
                ->native(false)
                ->selectablePlaceholder(fn (Select $component) => ! $component->isRequired());
        });
    }
}
