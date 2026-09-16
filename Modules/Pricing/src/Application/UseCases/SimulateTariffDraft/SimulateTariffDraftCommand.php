<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\SimulateTariffDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class SimulateTariffDraftCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $versionId,
        public array $input,
        public int $expectedVersion,
    )
    {
    }
}
