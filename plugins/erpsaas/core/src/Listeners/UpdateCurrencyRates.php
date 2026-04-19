<?php

namespace Erpsaas\Core\Listeners;

use Erpsaas\Core\Contracts\CurrencyHandler;
use Erpsaas\Core\Events\DefaultCurrencyChanged;
use Erpsaas\Core\Facades\Forex;
use Erpsaas\Core\Models\Setting\Currency;
use Illuminate\Support\Facades\DB;

readonly class UpdateCurrencyRates
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private CurrencyHandler $currencyService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(DefaultCurrencyChanged $event): void
    {
        DB::transaction(function () use ($event) {
            $defaultCurrency = $event->currency;

            if (bccomp((string) $defaultCurrency->rate, '1.0', 8) !== 0) {
                $defaultCurrency->update(['rate' => 1]);
            }

            if (Forex::isEnabled()) {
                $this->updateOtherCurrencyRates($defaultCurrency);
            }
        });
    }

    private function updateOtherCurrencyRates(Currency $defaultCurrency): void
    {
        $targetCurrencies = Currency::where('code', '!=', $defaultCurrency->code)
            ->pluck('code')
            ->toArray();

        $exchangeRates = $this->currencyService->getCachedExchangeRates($defaultCurrency->code, $targetCurrencies);

        foreach ($exchangeRates as $currencyCode => $newRate) {
            $currency = Currency::where('code', $currencyCode)->first();

            if ($currency && bccomp((string) $currency->rate, (string) $newRate, 8) !== 0) {
                $currency->update(['rate' => $newRate]);
            }
        }
    }
}
