<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Application\CommitmentClock;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiException;

final readonly class ListPickupCommitmentWindowsHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
        private \Modules\ServiceCatalog\Application\Services\ScheduleWindows $scheduleWindows,
    )
    {
    }

    public function handle(ListPickupCommitmentWindowsCommand $command): ListPickupCommitmentWindowsResult
    {
        return new ListPickupCommitmentWindowsResult($this->execute($command->actor, $command->nodeId, $command->at));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, ?string $at = null): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.resolve', true);
        $now = CarbonImmutable::parse($at ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString());
        $versionIds = $this->schedules->pickupVersions($actor->hqId, $nodeId);
        $results = [];
        foreach ($versionIds as $versionId) {
            $version = (array) $this->schedules->version($versionId);
            if (!empty($version['commitment_policy'])) {
                $policy = json_decode($version['commitment_policy'], true);
                if ($policy['pickup']['mode'] !== 'SELECTABLE_WINDOW') {
                    continue;
                }
                $configured = array_map(fn($w) => $this->scheduleReader->decodeWindow((array) $w), $this->schedules->windows($versionId));
                for ($offset = 0; $offset < 14; $offset++) {
                    try {
                        $resolved = (new CommitmentClock())->resolve($policy['pickup'], $configured, [
                            'acceptance_at' => $now->toISOString(),
                            'pickup_service_date' => $now->setTimezone($version['timezone'])->addDays($offset)->toDateString(),
                        ], $version['timezone'], (bool) $policy['include_holidays'], false, 'PICKUP');
                    } catch (ApiException $error) {
                        if (($error->details['reason_code'] ?? null) === 'SLA_CALENDAR_UNAVAILABLE') {
                            break;
                        }
                        throw $error;
                    }
                    if (!empty($resolved['windows'])) {
                        foreach ($resolved['windows'] as $window) {
                            $results[] = ['commitment_schedule_version_id' => $versionId, ...$window];
                        }
                        break;
                    }
                }
                continue;
            }
            foreach ($this->schedules->pickupWindows($versionId) as $window) {
                $instance = $this->scheduleWindows->nextWindow((array) $window, (string) $version['timezone'], $now);
                if ($instance !== null) {
                    $results[] = ['commitment_schedule_version_id' => $versionId, ...$instance];
                }
            }
        }
        usort($results, fn($left, $right) => strcmp((string) $left['starts_at'], (string) $right['starts_at']));
        return $results;
    }
}
