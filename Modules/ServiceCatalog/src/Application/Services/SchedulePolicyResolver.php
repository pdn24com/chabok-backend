<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentClockInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\SchedulePolicyResolverInterface;
use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationsDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Application\Mappers\CommitmentInput;
use Modules\ServiceCatalog\Domain\Enums\CommitmentEvidenceKind;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final readonly class SchedulePolicyResolver implements SchedulePolicyResolverInterface
{
    public function __construct(
        private CommitmentClockInterface $commitmentClock,
        private CommitmentZoneResolverInterface $commitmentZoneResolver,
        private ClockInterface $clock,
    ) {}

    public function resolvePolicy(
        CommitmentScheduleVersionRecord $version,
        OfferingSelectionContext $context,
        bool $requireSelection,
        string $hqId,
        ?CommitmentDestinationsDto $destinations = null,
    ): OfferingCommitmentDto {
        $policy = $version->commitment_policy;
        $selectedRule = null;
        $destination = null;
        $delivery = $policy['delivery'];
        if (! empty($policy['zone_set_id'])) {
            $destinations ??= $this->commitmentZoneResolver->destinations($hqId, [$policy['zone_set_id']], $context->commitmentDestination());
            $destination = $destinations->forGroup($policy['zone_set_id']);
            foreach ($policy['destination_rules'] as $rule) {
                if ($destination->match?->code === $rule['destination_zone_code']) {
                    $selectedRule = $rule['id'];
                    $delivery = $rule['policy'];
                }
            }
        }
        if ($delivery === null) {
            return new OfferingCommitmentDto(CommitmentEvidenceKind::TimingPolicy, false, 'DELIVERY_COMMITMENT_UNCONFIGURED', schedule: $version, destination: $destination);
        }
        $timezone = (string) $version->timezone;
        $clockWindows = $version->windows->map(CommitmentInput::windowRecord(...))->all();
        $clockContext = CommitmentInput::context($context, $this->clock->now());
        $pickup = $this->commitmentClock->resolve(CommitmentInput::timingPolicy($policy['pickup']), $clockWindows, $clockContext,
            $timezone, (bool) $policy['include_holidays'], $requireSelection, CommitmentWindowType::Pickup);
        $result = $this->commitmentClock->resolve(CommitmentInput::timingPolicy($delivery), $clockWindows, $clockContext->withPickup($pickup),
            $timezone, (bool) $policy['include_holidays'], $requireSelection, CommitmentWindowType::Delivery);

        return new OfferingCommitmentDto(CommitmentEvidenceKind::TimingPolicy, schedule: $version, pickup: $pickup, delivery: $result,
            policy: $policy, deliveryPolicy: $delivery, selectedRuleId: $selectedRule, destination: $destination,
            acceptedAt: $context->acceptanceAt ?? $clockContext->acceptedAt->utc()->toISOString(), requestedDeliveryWindowCode: $context->deliveryWindowCode);
    }
}
