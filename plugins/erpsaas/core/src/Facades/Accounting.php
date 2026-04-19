<?php

namespace Erpsaas\Core\Facades;

use Erpsaas\Core\Services\AccountService;
use Illuminate\Support\Facades\Facade;

class Accounting extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AccountService::class;
    }
}
