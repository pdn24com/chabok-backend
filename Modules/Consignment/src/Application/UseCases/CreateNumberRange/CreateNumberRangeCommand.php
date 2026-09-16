<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateNumberRangeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
