<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

final readonly class ProfileAddressDto
{
    public function __construct(public string $countryCode, public ?string $provinceId, public ?string $cityId, public ?string $postalCode, public ?string $line, public ?float $latitude, public ?float $longitude) {}
}
