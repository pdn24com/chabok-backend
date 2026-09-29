<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\CreateManifest;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestContextInputDto;

final readonly class CreateManifestCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public ManifestContextInputDto $input,
        public string $correlationId,
    ) {}
}
