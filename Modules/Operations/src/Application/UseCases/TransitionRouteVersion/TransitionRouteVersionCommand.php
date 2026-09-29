<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionRouteVersion;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class TransitionRouteVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $definitionId,
        public string $versionId,
        public string $action,
        public int $expected,
        public ?string $note,
        public string $correlationId,
    ) {}
}
