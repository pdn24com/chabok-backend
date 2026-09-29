<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\TariffDraftDto;

interface PricingDraftPreparationInterface
{
    public function prepareTariffDraft(AuthenticatedPrincipal $actor, TariffDraftDto $input): TariffDraftDto;

    public function assertTariffReferences(AuthenticatedPrincipal $actor, TariffDraftDto $input): void;
}
