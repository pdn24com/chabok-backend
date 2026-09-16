<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\AddManifestParcels;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AddManifestParcelsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
