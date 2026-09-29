<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\SimulateTariffDraft;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\QuoteInputDto;

final readonly class SimulateTariffDraftCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $versionId,
        public QuoteInputDto $input,
        public int $expectedVersion,
    ) {}
}
