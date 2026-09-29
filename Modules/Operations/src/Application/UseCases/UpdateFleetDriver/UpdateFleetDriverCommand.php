<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetDriver;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\DriverChangesDto;

final readonly class UpdateFleetDriverCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $driverId,
        public DriverChangesDto $input,
        public string $correlationId,
    ) {}
}
