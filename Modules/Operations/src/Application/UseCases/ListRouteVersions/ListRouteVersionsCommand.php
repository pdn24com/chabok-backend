<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteVersions;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListRouteVersionsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $definitionId,
        public int $page = 1,
        public int $perPage = 20,
    ) {}
}
