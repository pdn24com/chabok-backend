<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateArea;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateAreaCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $areaId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
