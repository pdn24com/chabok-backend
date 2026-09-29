<?php

declare(strict_types=1);

namespace Modules\Dashboard\Domain\Definitions;

use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Enums\ManifestTransition;

/** Dashboard-facing projections of the operational vocabularies the other modules own. */
final class DashboardMetricDefinitions
{
    /** @var list<string> */
    public const SAFE_AUDIT_ACTIONS = [
        'CONSIGNMENT_CREATED',
        'CONSIGNMENT_UPDATED',
        'MANIFEST_CREATED',
        'MANIFEST_CONTEXT_UPDATED',
        'MANIFEST_PARCELS_ADDED',
        'MANIFEST_VALIDATED',
        'MANIFEST_CONFIRMED',
    ];

    public const MANIFEST_AUDIT_ACTIONS = ['MANIFEST_CREATED', 'MANIFEST_CONTEXT_UPDATED', 'MANIFEST_PARCELS_ADDED', 'MANIFEST_VALIDATED', 'MANIFEST_CONFIRMED'];

    /** Manifest targets a Node can raise a Manifest for. */
    private const MANIFEST_TARGETS = [ManifestTransition::Reception, ManifestTransition::OutboundConfirmation, ManifestTransition::DeliveryAssignment];

    /** @return list<string> */
    public static function consignmentStatuses(): array
    {
        return ConsignmentStatus::values();
    }

    /** @return list<string> */
    public static function terminalConsignmentStatuses(): array
    {
        return ConsignmentStatus::valuesOf(ConsignmentStatus::terminal());
    }

    /** @return list<string> */
    public static function activeConsignmentStatuses(): array
    {
        return ConsignmentStatus::valuesOf(ConsignmentStatus::active());
    }

    public static function isActiveConsignmentStatus(string $status): bool
    {
        return ConsignmentStatus::tryFrom($status)?->isActive() ?? false;
    }

    /** @return list<string> */
    public static function manifestStates(): array
    {
        return ManifestState::values();
    }

    /** @return list<string> */
    public static function manifestTargetStatuses(): array
    {
        return array_column(self::MANIFEST_TARGETS, 'value');
    }
}
