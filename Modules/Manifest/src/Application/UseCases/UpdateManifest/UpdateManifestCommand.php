<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\UpdateManifest;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestContextInputDto;

final readonly class UpdateManifestCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public ManifestContextInputDto $input,
        public string $correlationId,
    ) {}
}
