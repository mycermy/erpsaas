<?php

namespace Erpsaas\Core\DTO;

class ReportTypeDTO
{
    public function __construct(
        public ?array $header = null,
        public ?array $data = null,
        public ?array $summary = null,
    ) {}
}
