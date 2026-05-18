<?php

namespace Erpsaas\Core\DTO;

readonly class VendorPreviewDTO extends VendorDTO
{
    public static function fake(): self
    {
        return new self(
            name: 'ABC Supplies Inc.',
            addressLine1: '5678 Oak Ave',
            addressLine2: 'Building B',
            city: 'Commerce City',
            state: 'Colorado',
            postalCode: '80022',
            country: 'United States',
        );
    }
}
