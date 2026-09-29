<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Dto;

final readonly class NodeAddressDto
{
    public function __construct(public ?string $countryCode, public ?string $provinceId, public ?string $cityId, public ?string $postalCode, public ?string $line, public ?float $latitude, public ?float $longitude) {}
}
