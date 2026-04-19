<?php

namespace Erpsaas\Core\Faker;

use Erpsaas\Core\Models\Locale\Country;
use Faker\Provider\Base;
use OutOfBoundsException;

class CurrencyCode extends Base
{
    public function currencyCode(string $countryCode): string
    {
        try {
            return Country::where('id', $countryCode)->pluck('currency_code')->first();
        } catch (OutOfBoundsException $e) {
            return 'USD';
        }
    }
}
