<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ManifestPolicy
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'PD' => ['CFM'],
        'PU' => ['PD'],
        'NPU' => ['PD'],
        'IR' => ['PU', 'OS'],
        'ROU' => ['IR'],
        'OF' => ['ROU'],
        'OS' => ['OF'],
        'OD' => ['IR'],
        'OK' => ['OD'],
        'NOK' => ['OD'],
    ];

    public function assertEditable(string $state): void
    {
        if (! in_array($state, ['DRAFT', 'OPEN'], true)) {
            throw new ApiException(
                ApiErrorCode::ManifestNotEditable,
                422,
                'The Manifest can no longer be edited.',
            );
        }
    }

    public function assertContext(string $targetStatus, ?string $driverId): void
    {
        if (! isset(self::TRANSITIONS[$targetStatus])) {
            throw new ApiException(
                ApiErrorCode::UnsupportedManifestTransition,
                422,
                'The Manifest target status is not available.',
            );
        }
        if (in_array($targetStatus, ['PD', 'OD', 'OS'], true) && $driverId === null) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'An assigned driver is required for this target status.',
                ['assigned_driver_id' => ['An assigned driver is required.']],
            );
        }
    }

    public function canTransition(string $currentStatus, string $targetStatus): bool
    {
        return in_array($currentStatus, self::TRANSITIONS[$targetStatus] ?? [], true);
    }

    /** @return list<string> */
    public function sourceStatuses(string $targetStatus): array
    {
        return self::TRANSITIONS[$targetStatus] ?? [];
    }
}
