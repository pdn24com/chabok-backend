<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListTariffs;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListTariffsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
