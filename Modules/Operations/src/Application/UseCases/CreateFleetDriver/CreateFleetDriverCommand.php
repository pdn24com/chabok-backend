<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetDriver;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\DriverCreationDto;

final readonly class CreateFleetDriverCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public DriverCreationDto $input,
        public string $correlationId,
    ) {}
}
