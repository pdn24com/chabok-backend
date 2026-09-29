<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Consignment\Application\Contracts\ConsignmentAggregateProjectorInterface;
use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Consignment\Application\Dto\ParcelTransitionDto;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\ParcelLifecycleServiceInterface;

final readonly class ParcelLifecycleService implements ParcelLifecycleServiceInterface
{
    public function __construct(
        private ConsignmentLedgerAccessInterface $consignmentLedgerAccess,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private ConsignmentAggregateProjectorInterface $consignmentAggregateProjector,
    ) {}

    /** @return list<string> parcel IDs */
    public function transition(
        AuthenticatedPrincipal $actor,
        string $consignmentId,
        string $from,
        string $to,
        string $command,
        ?string $nodeId,
        string $custodyType,
        ?string $custodianId,
        string $correlationId,
        ?string $driverId = null,
        ?string $manifestId = null,
        ?string $reasonCode = null,
        ?string $safeNote = null,
        ?string $routePlanId = null,
        ?string $routePlanLegId = null,
    ): array {
        if ($this->consignmentLedgerAccess->lockConsignment($actor->hqId, $consignmentId) === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $parcels = $this->consignmentLedgerAccess->lockParcels($actor->hqId, $consignmentId);
        if ($parcels->isEmpty() || $parcels->contains(fn ($parcel): bool => $parcel->current_status !== $from)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.transition_is_not_allowed', messageParams: ['from' => $from, 'to' => $to]);
        }
        $this->consignmentLedgerAccess->applyParcelTransition((string) $actor->hqId, $consignmentId, $parcels, new ParcelTransitionDto(
            fromStatus: $from,
            toStatus: $to,
            command: $command,
            nodeId: $nodeId,
            custodyType: $custodyType,
            custodianId: $custodianId,
            actorId: $actor->userId,
            correlationId: $correlationId,
            driverId: $driverId,
            manifestId: $manifestId,
            reasonCode: $reasonCode ?? $command,
            safeNote: $safeNote,
            routePlanId: $routePlanId,
            routePlanLegId: $routePlanLegId,
        ));
        $this->consignmentAggregateProjector->project($actor, $consignmentId, $to, $nodeId, $manifestId, $reasonCode ?? $command, $driverId, $correlationId);
        $this->auditWriter->write($actor->hqId, $actor->userId, $command, 'CONSIGNMENT', $consignmentId, $correlationId, ['status' => $from], ['status' => $to, 'custody_type' => $custodyType]);
        $this->outboxWriter->write($actor->hqId, 'CONSIGNMENT', $consignmentId, 'operations.command.executed', $correlationId, [
            'command' => $command,
            'resource_id' => $consignmentId,
            'consignment_id' => $consignmentId,
            'status' => $to,
        ]);

        return $parcels->pluck('parcel_id')->all();
    }
}
