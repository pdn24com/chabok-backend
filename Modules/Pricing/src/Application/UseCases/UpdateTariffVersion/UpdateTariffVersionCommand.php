<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdateTariffVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateTariffVersionCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $versionId, public array $input)
    {
    }
}
