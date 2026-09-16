<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreateTariff;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateTariffCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
