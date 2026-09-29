<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableDrivers;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Domain\Enums\DriverCapability;

final readonly class ListAvailableDriversCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public ?DriverCapability $capability,
    ) {}
}
