<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Enums\ManifestParcelAddResult;

final class ManifestParcelOutcomeDto
{
    public function __construct(
        public string $input,
        public ManifestParcelAddResult $result,
        public ManifestEligibilityReason $reason,
        public ?string $parcelNumber = null,
    ) {}
}
