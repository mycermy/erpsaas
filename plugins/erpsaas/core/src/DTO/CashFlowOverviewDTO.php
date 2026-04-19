<?php

namespace Erpsaas\Core\DTO;

class CashFlowOverviewDTO
{
    public function __construct(
        public array $categories,
    ) {}
}
