<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetArea;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetAreaCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $areaId) {}
}
