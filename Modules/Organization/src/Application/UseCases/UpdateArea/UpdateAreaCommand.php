<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateArea;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Dto\AreaChangesDto;

final readonly class UpdateAreaCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $areaId,
        public AreaChangesDto $input,
        public string $correlationId,
    ) {}
}
