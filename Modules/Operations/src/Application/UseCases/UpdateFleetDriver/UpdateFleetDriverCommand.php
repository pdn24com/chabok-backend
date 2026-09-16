<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetDriver;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateFleetDriverCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $driverId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
