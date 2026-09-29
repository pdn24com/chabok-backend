<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentClockInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleWindowsInterface;
use Modules\ServiceCatalog\Application\Dto\PickupWindowOptionDto;
use Modules\ServiceCatalog\Application\Mappers\CommitmentInput;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CommitmentFailure;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentRuleViolation;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentContext;

final readonly class ListPickupCommitmentWindowsHandler
{
    public function __construct(
        private CommitmentClockInterface $commitmentClock,
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private ClockInterface $clock,
        private ScheduleReaderInterface $scheduleReader,
        private ScheduleWindowsInterface $scheduleWindows,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    /** @return list<PickupWindowOptionDto> */
    public function handle(ListPickupCommitmentWindowsCommand $command): array
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $at = $command->at;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.resolve', true);
        $now = CarbonImmutable::parse($at ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString());
        $versions = $this->commitmentScheduleRepository->publishedVersionsScopedToNode($actor->hqId, $nodeId);
        $results = [];
        foreach ($versions as $version) {
            $versionId = $version->commitment_schedule_version_id;
            if (! empty($version->commitment_policy)) {
                $policy = $version->commitment_policy;
                $pickupPolicy = CommitmentInput::timingPolicy($policy['pickup']);
                if ($pickupPolicy->mode !== CommitmentMode::SelectableWindow) {
                    continue;
                }
                $configured = $version->windows->map(CommitmentInput::windowRecord(...))->all();
                for ($offset = 0; $offset < 14; $offset++) {
                    try {
                        $resolved = $this->commitmentClock->resolve($pickupPolicy, $configured, new CommitmentContext(acceptedAt: $now,
                            pickupServiceDate: $now->setTimezone($version->timezone)->addDays($offset)->toDateString()), $version->timezone, (bool) $policy['include_holidays'], false, CommitmentWindowType::Pickup);
                    } catch (CommitmentRuleViolation $error) {
                        if ($error->reason === CommitmentFailure::CalendarUnavailable) {
                            break;
                        }
                        throw $error;
                    }
                    if ($resolved->windows !== []) {
                        foreach ($resolved->windows as $window) {
                            $results[] = new PickupWindowOptionDto($versionId, $window);
                        }
                        break;
                    }
                }

                continue;
            }
            foreach ($version->windows->where('window_type', 'PICKUP')->filter(fn ($window) => (bool) $window->active)->sortBy('start_time') as $window) {
                $instance = $this->scheduleWindows->nextWindow($window, (string) $version->timezone, $now);
                if ($instance !== null) {
                    $results[] = new PickupWindowOptionDto($versionId, $instance);
                }
            }
        }
        usort($results, fn (PickupWindowOptionDto $left, PickupWindowOptionDto $right): int => $left->window->startsAt <=> $right->window->startsAt);

        return $results;
    }
}
