<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Dto;

use Modules\Organization\Domain\Enums\NetworkStatus;
use Modules\Organization\Domain\Enums\NodeCapability;
use Modules\Organization\Domain\Enums\NodeType;

final readonly class NodeChangesDto
{
    /** @param list<NodeCapability>|null $capabilities */
    public function __construct(public int $expectedVersion, public ?string $areaId, public ?string $title, public ?NodeType $type, public ?array $capabilities, public ?NodeAddressDto $address, public ?NetworkStatus $status) {}
}
