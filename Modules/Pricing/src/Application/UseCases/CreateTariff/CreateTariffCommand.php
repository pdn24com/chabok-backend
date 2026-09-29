<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreateTariff;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\TariffDraftDto;

final readonly class CreateTariffCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public TariffDraftDto $input,
        public string $correlationId,
    ) {}
}
