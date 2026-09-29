<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\DriverCapability;

final readonly class DriverCreationDto
{
    /** @param list<DriverCapability> $capabilities */
    public function __construct(
        public string $driverCode,
        public string $displayName,
        public string $homeNodeId,
        public array $capabilities,
        public ?string $userId,
        public ?string $mobile,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            driverCode: trim($input['driver_code']),
            displayName: trim($input['display_name']),
            homeNodeId: $input['home_node_id'],
            capabilities: array_map(DriverCapability::from(...), array_values($input['capabilities'])),
            userId: $input['user_id'] ?? null,
            mobile: $input['mobile'] ?? null,
        );
    }
}
