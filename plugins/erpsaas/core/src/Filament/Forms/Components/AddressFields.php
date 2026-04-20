<?php

namespace Erpsaas\Core\Filament\Forms\Components;

use Closure;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;

class AddressFields extends Grid
{
    protected bool $isSoftRequired = false;

    protected bool | Closure $isCountryDisabled = false;

    protected bool | Closure $isRequired = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            TextInput::make('address_line_1')
                ->label('Address line 1')
                ->required(fn() => $this->isRequired())
                ->markAsRequired(fn() => $this->isRequired() && ! $this->isSoftRequired)
                ->maxLength(255),
            TextInput::make('address_line_2')
                ->label('Address line 2')
                ->maxLength(255),
            CountrySelect::make('country_code')
                ->disabled(fn() => $this->isCountryDisabled())
                ->clearStateField()
                ->required(fn() => $this->isRequired())
                ->markAsRequired(fn() => $this->isRequired() && ! $this->isSoftRequired),
            StateSelect::make('state_id'),
            TextInput::make('city')
                ->label('City')
                ->required(fn() => $this->isRequired())
                ->markAsRequired(fn() => $this->isRequired() && ! $this->isSoftRequired)
                ->maxLength(255),
            TextInput::make('postal_code')
                ->label('Postal code')
                ->maxLength(255),
        ]);
    }

    public function softRequired(bool $condition = true): static
    {
        $this->isSoftRequired = $condition;

        return $this;
    }

    protected function setSoftRequired(bool $condition): void
    {
        $this->isSoftRequired = $condition;
    }

    public function required(bool | Closure $condition = true): static
    {
        $this->isRequired = $condition;

        return $this;
    }

    public function isRequired(): bool
    {
        return (bool) $this->evaluate($this->isRequired);
    }

    public function disabledCountry(bool | Closure $condition = true): static
    {
        $this->isCountryDisabled = $condition;

        return $this;
    }

    public function isCountryDisabled(): bool
    {
        return $this->evaluate($this->isCountryDisabled);
    }
}
