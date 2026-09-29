<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Policies;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Manifest\Domain\Exceptions\ManifestRuleViolation;

final class ManifestPolicy
{
    public function assertEditable(string $state): void
    {
        if (ManifestState::tryFrom($state)?->isEditable() !== true) {
            throw new ManifestRuleViolation(ApiErrorCode::ManifestNotEditable, 'manifest.manifest_can_no_longer_be_edited');
        }
    }

    public function assertContext(string $targetStatus, ?string $driverId): void
    {
        if (ManifestTransition::tryFrom($targetStatus) === null) {
            throw new ManifestRuleViolation(ApiErrorCode::UnsupportedManifestTransition, 'manifest.manifest_target_status_is_not_available');
        }
        if (ManifestTransition::from($targetStatus)->requiredDriverCapability() !== null && $driverId === null) {
            throw new ManifestRuleViolation(ApiErrorCode::ValidationError, 'manifest.assigned_driver_is_required_for_target_status', ['assigned_driver_id' => ['manifest.assigned_driver_is_required']]);
        }
    }

    public function canTransition(string $currentStatus, string $targetStatus): bool
    {
        return in_array($currentStatus, $this->sourceStatuses($targetStatus), true);
    }

    /** @return list<string> */
    public function sourceStatuses(string $targetStatus): array
    {
        return ManifestTransition::tryFrom($targetStatus)?->sourceStatuses() ?? [];
    }

    public function assertVersion(int $currentVersion, int $expectedVersion): void
    {
        if ($currentVersion !== $expectedVersion) {
            throw new ManifestRuleViolation(ApiErrorCode::ManifestVersionConflict, 'manifest.manifest_version_is_stale', details: ['current_version' => $currentVersion]);
        }
    }
}
