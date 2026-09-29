<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

use Modules\Iam\Domain\Enums\OperationalProfileKind;
use Modules\Iam\Domain\Enums\OperationalProfileMode;

final readonly class OperationalProfileInputDto
{
    public function __construct(public OperationalProfileKind $kind, public OperationalProfileMode $mode, public ?string $existingId, public ?int $expectedVersion, public ?string $roleId, public ?DriverProfileDraftDto $driver, public ?NodeProfileDraftDto $node) {}
}
