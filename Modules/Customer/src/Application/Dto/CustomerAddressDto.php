<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

final readonly class CustomerAddressDto
{
    public function __construct(
        public string $countryCode,
        public ?string $provinceId = null,
        public ?string $cityId = null,
        public ?string $foreignCity = null,
        public ?string $postalCode = null,
        public ?string $addressText = null,
    ) {}
}
