<?php

namespace Erpsaas\Core\DTO;

class AccountTypeDTO
{
    /**
     * @param  AccountDTO[]  $accounts
     */
    public function __construct(
        public array $accounts,
        public AccountBalanceDTO $summary,
    ) {}
}
