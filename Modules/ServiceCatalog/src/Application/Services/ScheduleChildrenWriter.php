<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

final readonly class ScheduleChildrenWriter
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function replaceChildren(string $versionId, string $hqId, array $input): void
    {
        $this->schedules->deleteWindows($versionId);
        $this->schedules->deleteScopes($versionId);
        foreach ((array) ($input['windows'] ?? []) as $window) {
            $this->schedules->insertWindow([
                'commitment_schedule_window_id' => $this->identifiers->uuid(),
                'commitment_schedule_version_id' => $versionId,
                'window_code' => mb_strtoupper((string) $window['window_code']),
                'window_type' => $window['window_type'],
                'label_fa' => $window['label_fa'],
                'risk_threshold_minutes' => $window['risk_threshold_minutes'] ?? 120,
                'start_time' => $window['start_time'],
                'end_time' => $window['end_time'],
                'booking_cutoff_time' => $window['booking_cutoff_time'],
                'applicable_weekdays' => json_encode(array_values((array) $window['applicable_weekdays']), JSON_THROW_ON_ERROR),
                'day_offset' => $window['day_offset'] ?? 0,
                'active' => $window['active'] ?? true,
            ]);
        }
        foreach ((array) ($input['scopes'] ?? [['scope_type' => 'HQ']]) as $scope) {
            $this->schedules->insertScope([
                'commitment_schedule_scope_id' => $this->identifiers->uuid(),
                'commitment_schedule_version_id' => $versionId,
                'hq_id' => $hqId,
                'scope_type' => $scope['scope_type'],
                'node_id' => $scope['scope_type'] === 'NODE' ? $scope['node_id'] : null,
            ]);
        }
    }
}
