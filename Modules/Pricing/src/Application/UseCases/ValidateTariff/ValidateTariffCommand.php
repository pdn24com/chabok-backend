<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidateTariff;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateTariffCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $versionId)
    {
    }
}
