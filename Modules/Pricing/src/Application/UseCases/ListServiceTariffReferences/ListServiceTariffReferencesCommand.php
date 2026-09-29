<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListServiceTariffReferences;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListServiceTariffReferencesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor) {}
}
