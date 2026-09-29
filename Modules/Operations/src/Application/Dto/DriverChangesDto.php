<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;

final readonly class DriverChangesDto
{
    /** @param list<DriverCapability> $capabilities */
    public function __construct(
        public int $expectedVersion,
        public ?string $displayName,
        public ?string $homeNodeId,
        public ?array $capabilities,
        public ?string $userId,
        public bool $userIdProvided,
        public ?string $mobile,
        public bool $mobileProvided,
        public ?FleetStatus $status,
        public ?FleetAvailability $availabilityStatus,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            expectedVersion: (int) $input['expected_version'],
            displayName: isset($input['display_name']) ? trim($input['display_name']) : null,
            homeNodeId: $input['home_node_id'] ?? null,
            capabilities: isset($input['capabilities']) ? array_map(DriverCapability::from(...), array_values($input['capabilities'])) : null,
            userId: $input['user_id'] ?? null,
            userIdProvided: array_key_exists('user_id', $input),
            mobile: $input['mobile'] ?? null,
            mobileProvided: array_key_exists('mobile', $input),
            status: isset($input['status']) && $input['status'] !== '' ? FleetStatus::from($input['status']) : null,
            availabilityStatus: isset($input['availability_status']) && $input['availability_status'] !== '' ? FleetAvailability::from($input['availability_status']) : null,
        );
    }
}
