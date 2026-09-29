<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingCommitmentResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\SchedulePolicyResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleWindowsInterface;
use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationsDto;
use Modules\ServiceCatalog\Application\Dto\LegacyCommitmentPromiseDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentResolutionDto;
use Modules\ServiceCatalog\Domain\Enums\CommitmentEvidenceKind;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;
use Modules\ServiceCatalog\Domain\Enums\CommitmentUnavailability;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowUnavailability;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentRuleViolation;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentWindowUnavailable;
use Modules\ServiceCatalog\Domain\Exceptions\OfferingCommitmentUnavailable;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingCommitmentBindingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final readonly class OfferingCommitmentResolver implements OfferingCommitmentResolverInterface
{
    public function __construct(
        private SchedulePolicyResolverInterface $schedulePolicyResolver,
        private ClockInterface $clock,
        private ScheduleWindowsInterface $scheduleWindows,
    ) {}

    /** Report whether an Offering can promise a commitment, so listing flows can omit it without an error. */
    public function inspect(ServiceOfferingVersionRecord $offering, OfferingSelectionContext $context, bool $requireSelection = true, ?CommitmentDestinationsDto $destinations = null): OfferingCommitmentResolutionDto
    {
        try {
            return OfferingCommitmentResolutionDto::promised($this->resolve($offering, $context, $requireSelection, $destinations));
        } catch (OfferingCommitmentUnavailable $unavailable) {
            return OfferingCommitmentResolutionDto::unavailable($unavailable->reason);
        } catch (CommitmentWindowUnavailable $window) {
            return $window->reason === CommitmentWindowUnavailability::WindowInvalid
                ? OfferingCommitmentResolutionDto::unavailable(CommitmentUnavailability::fromWindowType($window->windowType))
                : throw $window;
        } catch (CommitmentRuleViolation $violation) {
            $reason = CommitmentUnavailability::tryFromRuleViolation($violation->reason);

            return $reason === null ? throw $violation : OfferingCommitmentResolutionDto::unavailable($reason);
        }
    }

    /** Resolve a preloaded offering graph without querying each bound schedule again. */
    public function resolve(ServiceOfferingVersionRecord $offering, OfferingSelectionContext $context, bool $requireSelection = true, ?CommitmentDestinationsDto $destinations = null): ?OfferingCommitmentDto
    {
        $binding = $offering->commitmentBinding;
        if ($binding === null) {
            return null;
        }
        $owner = $offering->hq_id;
        $schedule = $binding->scheduleVersion?->schedule;
        $version = $schedule?->publishedVersion;
        if ($version === null || $schedule->status !== 'ACTIVE' || $owner !== null && $schedule->hq_id !== null && $schedule->hq_id !== $owner) {
            throw new OfferingCommitmentUnavailable(CommitmentUnavailability::CatalogDependencyUnavailable);
        }
        $scopes = $version->scopes;
        if (! $scopes->contains(fn ($scope) => $scope->scope_type === 'HQ' || in_array($scope->node_id, $context->scheduleNodeIds ?? [], true))) {
            throw new OfferingCommitmentUnavailable(CommitmentUnavailability::CommitmentScopeUnavailable);
        }
        if (! empty($version->commitment_policy)) {
            return $this->schedulePolicyResolver->resolvePolicy($version, $context, $requireSelection, (string) $owner, $destinations);
        }
        $serviceDate = $context->pickupServiceDate ?? CarbonImmutable::instance($this->clock->now())->setTimezone($version->timezone)->toDateString();
        $pickup = $this->pickup($binding, $version, $context, $serviceDate, $requireSelection);
        if ($pickup->selectionRequired) {
            return new OfferingCommitmentDto(CommitmentEvidenceKind::BoundSchedule, false, 'PICKUP_WINDOW_REQUIRED', binding: $binding);
        }
        $delivery = $this->delivery($binding, $version, $context, $serviceDate, $pickup, $requireSelection);
        if ($delivery->selectionRequired) {
            return new OfferingCommitmentDto(CommitmentEvidenceKind::BoundSchedule, false, 'DELIVERY_WINDOW_REQUIRED', binding: $binding, pickup: $pickup, delivery: $delivery);
        }

        return new OfferingCommitmentDto(CommitmentEvidenceKind::BoundSchedule, schedule: $version, binding: $binding, pickup: $pickup, delivery: $delivery);
    }

    private function pickup(OfferingCommitmentBindingRecord $binding, CommitmentScheduleVersionRecord $version, OfferingSelectionContext $context, string $serviceDate, bool $requireSelection): LegacyCommitmentPromiseDto
    {
        $promise = new LegacyCommitmentPromiseDto(CommitmentMode::from($binding->pickup_mode));
        if ($promise->mode !== CommitmentMode::SelectableWindow) {
            return $promise;
        }
        $code = $context->pickupWindowCode ?? '';
        $promise->windows = $this->scheduleWindows->pickupWindowOptions($version->windows, $version->timezone, $context->pickupServiceDate !== null ? $serviceDate : null);
        $promise->selectionRequired = $code === '' && $requireSelection;
        if ($promise->selectionRequired) {
            return $promise;
        }
        $promise->selected = $code === '' ? null : $this->scheduleWindows->windowInstance($version->windows->firstWhere('window_code', $code), CommitmentWindowType::Pickup, $serviceDate, $version->timezone);
        $promise->flattenSelection = true;

        return $promise;
    }

    private function delivery(OfferingCommitmentBindingRecord $binding, CommitmentScheduleVersionRecord $version, OfferingSelectionContext $context, string $serviceDate, LegacyCommitmentPromiseDto $pickup, bool $requireSelection): LegacyCommitmentPromiseDto
    {
        $promise = new LegacyCommitmentPromiseDto(CommitmentMode::from($binding->delivery_mode));
        if ($promise->mode === CommitmentMode::SelectableWindow) {
            $promise->windows = $version->windows->where('window_type', CommitmentWindowType::Delivery->value)->filter(fn ($window) => (bool) $window->active)
                ->sortBy([['day_offset', 'asc'], ['start_time', 'asc']])
                ->map(fn ($window) => $this->scheduleWindows->windowInstance($window, CommitmentWindowType::Delivery, $serviceDate, $version->timezone))->values()->all();
            $code = $context->deliveryWindowCode ?? '';
            $promise->selectionRequired = $code === '' && $requireSelection;
            if (! $promise->selectionRequired) {
                $promise->selected = $code === '' ? null : $this->scheduleWindows->windowInstance($version->windows->firstWhere('window_code', $code), CommitmentWindowType::Delivery, $serviceDate, $version->timezone);
            }
        } elseif ($promise->mode === CommitmentMode::Computed) {
            $anchor = match ($binding->duration_anchor) {
                'CONSIGNMENT_CREATED' => CarbonImmutable::parse($context->acceptanceAt ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString())->utc(),
                'PICKUP_COMMITMENT_START' => $pickup->selected?->startsAt,
                'PICKUP_COMMITMENT_END' => $pickup->selected?->endsAt,
                default => null,
            };
            $promise->computedAt = $anchor === null ? null : match ($binding->duration_unit) {
                'MINUTE' => $anchor->addMinutes((int) $binding->duration_value),
                'DAY' => $anchor->addDays((int) $binding->duration_value),
                default => $anchor->addHours((int) $binding->duration_value),
            };
            $promise->anchor = (string) $binding->duration_anchor;
            $promise->durationValue = $binding->duration_value;
            $promise->durationUnit = $binding->duration_unit;
            $promise->awaitingOperation = $binding->duration_anchor === 'PICKUP_COMPLETED';
        }

        return $promise;
    }
}
