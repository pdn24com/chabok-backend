<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestContextDto;
use Modules\Manifest\Application\Dto\ManifestContextInputDto;

interface ManifestContextNormalizerInterface
{
    public function normalize(AuthenticatedPrincipal $actor, string $nodeId, ManifestContextInputDto $input): ManifestContextDto;
}
