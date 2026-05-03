<?php

namespace Erpsaas\Core\Utilities\Currency;

use Akaunting\Money\Currency as ISOCurrencies;
use Erpsaas\Core\Facades\Forex;
use Erpsaas\Core\Models\Setting\Currency;
use Erpsaas\Core\Services\CompanySettingsService;
use Illuminate\Support\Facades\Auth;

class CurrencyAccessor
{
    public static function getForexSupportedCurrencies(): ?array
    {
        return Forex::getSupportedCurrencies();
    }

    public static function getSupportedCurrencies(): array
    {
        $forexSupportedCurrencies = self::getForexSupportedCurrencies();
        $allCurrencies = self::getAllCurrencies();

        if (empty($forexSupportedCurrencies)) {
            return array_keys($allCurrencies);
        }

        return array_intersect($forexSupportedCurrencies, array_keys($allCurrencies));
    }

    public static function getAllCurrencies(): array
    {
        return ISOCurrencies::getCurrencies();
    }

    public static function getAllCurrencyOptions(): array
    {
        $allCurrencies = self::getSupportedCurrencies();

        return array_combine($allCurrencies, $allCurrencies);
    }

    public static function getAvailableCurrencies(): array
    {
        $supportedCurrencies = self::getSupportedCurrencies();

        $storedCurrencies = Currency::query()
            ->pluck('code')
            ->toArray();

        $availableCurrencies = array_diff($supportedCurrencies, $storedCurrencies);

        return array_combine($availableCurrencies, $availableCurrencies);
    }

    public static function getDefaultCurrency(): ?string
    {
        $companyId = Auth::user()?->current_company_id;

        if ($companyId === null) {
            return 'USD';
        }

        return CompanySettingsService::getDefaultCurrency($companyId);
    }
}
