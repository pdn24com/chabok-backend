<?php

declare(strict_types=1);

namespace Modules\Dashboard\Domain;

final class DashboardMetricDefinitions
{
    /** @var list<string> */
    public const CONSIGNMENT_STATUSES = [
        'D00', 'CFM', 'PD', 'PU', 'IR', 'ROU', 'OF', 'OS',
        'OD', 'OK', 'NPU', 'NOK', 'RH', 'RCH', 'RO', 'AA',
    ];

    /** @var list<string> */
    public const TERMINAL_CONSIGNMENT_STATUSES = ['OK', 'RO', 'AA'];

    /** @var list<string> */
    public const ACTIVE_CONSIGNMENT_STATUSES = [
        'D00', 'CFM', 'PD', 'PU', 'IR', 'ROU', 'OF', 'OS',
        'OD', 'NPU', 'NOK', 'RH', 'RCH',
    ];

    /** @var list<string> */
    public const MANIFEST_STATES = ['DRAFT', 'OPEN', 'CLOSED'];

    /** @var list<string> */
    public const MANIFEST_TARGET_STATUSES = ['IR', 'OF', 'OD'];

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

    /** @return list<string> */
    public static function activeConsignmentStatuses(): array
    {
        return self::ACTIVE_CONSIGNMENT_STATUSES;
    }

    public static function isActiveConsignmentStatus(string $status): bool
    {
        return in_array($status, self::ACTIVE_CONSIGNMENT_STATUSES, true);
    }
}
