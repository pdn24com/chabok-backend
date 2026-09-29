<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateArea;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Dto\AreaDraftDto;

final readonly class CreateAreaCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public AreaDraftDto $input,
        public string $correlationId,
    ) {}
}
