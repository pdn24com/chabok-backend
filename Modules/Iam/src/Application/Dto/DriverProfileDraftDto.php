<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

final readonly class DriverProfileDraftDto
{
    /** @param list<string> $capabilities */
    public function __construct(public string $code, public string $displayName, public string $homeNodeId, public array $capabilities, public ?string $mobile) {}
}
