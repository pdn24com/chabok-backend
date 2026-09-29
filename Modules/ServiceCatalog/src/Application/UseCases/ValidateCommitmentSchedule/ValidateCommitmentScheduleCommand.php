<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ValidateCommitmentScheduleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $versionId,
        public bool $automatic = false,
    ) {}
}
