<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestParcelTransitionDto;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface ManifestTransitionRecorderInterface
{
    /**
     * Caller owns the transaction and locks each consignment before its parcels.
     *
     * @param  list<ManifestParcelTransitionDto>  $transitions  Original parcel snapshots, before mutation.
     */
    public function recordTransitions(AuthenticatedPrincipal $actor, string $node, ManifestRecord $manifest, array $transitions, string $correlationId): void;

    public function commandEvent(AuthenticatedPrincipal $actor, string $resource, string $consignment, string $command, string $status, string $correlationId): void;
}
