<?php

namespace Erpsaas\Core\Contracts;

interface BalanceFormattable
{
    public static function fromArray(array $balances): static;
}
