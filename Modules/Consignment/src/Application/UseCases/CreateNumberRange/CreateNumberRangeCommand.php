<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateNumberRange;

use Modules\Consignment\Application\Dto\NumberRangeCreationDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateNumberRangeCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public NumberRangeCreationDto $input,
        public string $correlationId,
    ) {}
}
