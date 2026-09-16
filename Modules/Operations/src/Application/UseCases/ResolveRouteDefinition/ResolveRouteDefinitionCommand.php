<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveRouteDefinition;

use DateTimeInterface;

final readonly class ResolveRouteDefinitionCommand
{
    public function __construct(
        public string $hqId,
        public string $purpose,
        public string $originNodeId,
        public string $destinationNodeId,
        public ?string $offeringVersionId = null,
        public ?DateTimeInterface $at = null,
    )
    {
    }
}
