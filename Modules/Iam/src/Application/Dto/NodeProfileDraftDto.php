<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

final readonly class NodeProfileDraftDto
{
    /** @param list<string> $capabilities */
    public function __construct(public string $code, public string $title, public string $areaId, public string $type, public array $capabilities, public ProfileAddressDto $address) {}
}
