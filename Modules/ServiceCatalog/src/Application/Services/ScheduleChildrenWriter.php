<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\ServiceCatalog\Application\Contracts\ScheduleChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;
use Modules\ServiceCatalog\Application\Repositories\ScheduleChildrenRepositoryInterface;

final readonly class ScheduleChildrenWriter implements ScheduleChildrenWriterInterface
{
    public function __construct(
        private ScheduleChildrenRepositoryInterface $scheduleChildrenRepository,
    ) {}

    public function replaceChildren(
        string $versionId,
        string $hqId,
        CommitmentScheduleDto $input,
    ): void {
        $this->scheduleChildrenRepository->deleteWindows($versionId);
        $this->scheduleChildrenRepository->deleteScopes($versionId);
        $this->scheduleChildrenRepository->insertWindows(array_map(fn ($window): array => [

            'commitment_schedule_version_id' => $versionId,
            'window_code' => mb_strtoupper((string) $window->code),
            'window_type' => $window->type->value,
            'label_fa' => $window->label,
            'risk_threshold_minutes' => $window->riskThresholdMinutes,
            'start_time' => $window->startTime,
            'end_time' => $window->endTime,
            'booking_cutoff_time' => $window->bookingCutoffTime,
            'applicable_weekdays' => array_values((array) $window->weekdays),
            'day_offset' => $window->dayOffset,
            'active' => $window->active,
        ], $input->windows));
        $this->scheduleChildrenRepository->insertScopes(array_map(fn ($scope): array => [

            'commitment_schedule_version_id' => $versionId,
            'hq_id' => $hqId,
            'scope_type' => $scope->type->value,
            'node_id' => $scope->nodeId,
        ], $input->scopes));
    }
}
