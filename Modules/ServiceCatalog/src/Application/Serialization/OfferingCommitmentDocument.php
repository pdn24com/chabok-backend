<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Dto\LegacyCommitmentPromiseDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentDto;
use Modules\ServiceCatalog\Domain\Enums\CommitmentEvidenceKind;
use Modules\ServiceCatalog\Domain\Enums\CommitmentMode;

/** Stable quote evidence and HTTP representation, including legacy snapshots. */
final class OfferingCommitmentDocument
{
    public static function serialize(OfferingCommitmentDto $commitment): array
    {
        return match ($commitment->kind) {
            CommitmentEvidenceKind::BoundSchedule => self::boundSchedule($commitment),
            CommitmentEvidenceKind::TimingPolicy => self::timingPolicy($commitment),
            CommitmentEvidenceKind::LegacyDuration => self::legacyDuration($commitment),
        };
    }

    private static function boundSchedule(OfferingCommitmentDto $commitment): array
    {
        $result = ['eligible' => $commitment->eligible, 'reason_code' => $commitment->reasonCode];
        if ($commitment->eligible) {
            $result['schedule_version_id'] = $commitment->schedule->commitment_schedule_version_id;
            $result['timezone'] = $commitment->schedule->timezone;
        }

        return [...$result, 'binding' => $commitment->binding->attributesToArray(),
            'pickup' => $commitment->pickup === null ? null : self::legacyPromise($commitment->pickup),
            'delivery' => $commitment->delivery === null ? null : self::legacyPromise($commitment->delivery)];
    }

    private static function timingPolicy(OfferingCommitmentDto $commitment): array
    {
        $zone = $commitment->destination === null ? null : CommitmentDestinationDocument::serialize($commitment->destination);
        if (! $commitment->eligible) {
            return ['eligible' => false, 'reason_code' => $commitment->reasonCode,
                'schedule_version_id' => $commitment->schedule->commitment_schedule_version_id, 'destination_zone' => $zone];
        }

        return ['eligible' => true, 'reason_code' => null,
            'schedule_version_id' => $commitment->schedule->commitment_schedule_version_id, 'timezone' => $commitment->schedule->timezone,
            'accepted_at' => $commitment->acceptedAt, 'requested_delivery_window_code' => $commitment->requestedDeliveryWindowCode,
            'policy' => $commitment->policy, 'effective_delivery_policy' => $commitment->deliveryPolicy,
            'selected_rule_id' => $commitment->selectedRuleId, 'destination_zone' => $zone,
            'pickup' => CommitmentResolutionDocument::serialize($commitment->pickup, $commitment->policy['pickup']),
            'delivery' => CommitmentResolutionDocument::serialize($commitment->delivery, $commitment->deliveryPolicy),
            'windows_snapshot' => $commitment->schedule->windows->map(fn ($window) => $window->attributesToArray())->all()];
    }

    private static function legacyDuration(OfferingCommitmentDto $commitment): array
    {
        return ['commitment_type' => $commitment->legacyType, 'starts_at' => $commitment->legacyStartsAt->toISOString(),
            'delivery_commitment_at' => $commitment->legacyEndsAt->toISOString(), 'policy' => $commitment->policy,
            'pickup' => ['mode' => 'NONE'], 'delivery' => ['mode' => 'COMPUTED', 'computed_at' => $commitment->legacyEndsAt->toISOString()]];
    }

    private static function legacyPromise(LegacyCommitmentPromiseDto $promise): array
    {
        $result = ['mode' => $promise->mode->value];
        if ($promise->mode === CommitmentMode::SelectableWindow) {
            $result['windows'] = array_map(CommitmentResolutionDocument::window(...), $promise->windows);
            if ($promise->selectionRequired) {
                return $result;
            }
            $result['selected'] = $promise->selected === null ? null : CommitmentResolutionDocument::window($promise->selected);
            if ($promise->flattenSelection && $result['selected'] !== null) {
                $result = [...$result, ...$result['selected']];
            }
        } elseif ($promise->mode === CommitmentMode::Computed && $promise->anchor !== null) {
            $result += ['anchor' => $promise->anchor, 'duration_value' => $promise->durationValue, 'duration_unit' => $promise->durationUnit,
                'computed_at' => $promise->computedAt?->toISOString(), 'awaiting_operation' => $promise->awaitingOperation];
        }

        return $result;
    }
}
