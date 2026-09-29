<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleInputValidatorInterface;
use Modules\ServiceCatalog\Application\Contracts\SchedulePolicyInterface;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindow;

final readonly class SchedulePolicy implements SchedulePolicyInterface
{
    public function __construct(
        private CommitmentZoneResolverInterface $commitmentZoneResolver,
        private ScheduleInputValidatorInterface $scheduleInputValidator,
    ) {}

    /** @param list<CommitmentWindow> $windows */
    public function validate(
        array $policy,
        array $windows,
        string $hqId,
    ): array {
        $this->scheduleInputValidator->validate($policy);
        if ($policy['pickup']['anchor'] !== 'CONSIGNMENT_CREATED') {
            $this->reject('مبدأ جمع‌آوری باید پذیرش مرسوله باشد.');
        }
        $policies = [['PICKUP', $policy['pickup']]];
        if (! empty($policy['delivery'])) {
            $policies[] = ['DELIVERY', $policy['delivery']];
        }
        foreach ($policy['destination_rules'] as $r) {
            $policies[] = ['DELIVERY', $r['policy']];
        }
        foreach ($policies as [$kind, $p]) {
            if ($p['mode'] === 'SELECTABLE_WINDOW') {
                if ($kind === 'DELIVERY' && empty($p['window_codes'])) {
                    $this->reject('حداقل یک بازه برای تعهد توزیع انتخاب کنید.');
                }
                $available = [];
                foreach ($windows as $window) {
                    if ($window->type->value === $kind && $window->active) {
                        $available[] = $window->code;
                    }
                }
                if (! $available || array_diff($p['window_codes'], $available)) {
                    $this->reject('بازه‌های فعال و معتبر برای تعهد انتخاب کنید.');
                }
            }
            if ($kind === 'DELIVERY' && $p['mode'] !== 'NONE' && $policy['pickup']['mode'] === 'NONE' && in_array($p['anchor'], ['PICKUP_COMMITMENT_START', 'PICKUP_COMMITMENT_END'], true)) {
                $this->reject('مبدأ تعهد توزیع به تعهد جمع‌آوری موجود وابسته است.');
            }
        }
        if ($policy['destination_rules'] && empty($policy['zone_set_id'])) {
            $this->reject('گروه زون مقصد را انتخاب کنید.');
        }
        if (empty($policy['delivery']) && empty($policy['zone_set_id'])) {
            $this->reject('servicecatalog.default_commitment_or_zone_set_is_required');
        }
        if (! empty($policy['zone_set_id'])) {
            $group = $this->commitmentZoneResolver->group($hqId, $policy['zone_set_id']);
            foreach ($policy['destination_rules'] as $rule) {
                if (! in_array($rule['destination_zone_code'], $group->zoneCodes(), true)) {
                    $this->reject('servicecatalog.destination_zone_not_in_zone_set_version');
                }
            }
            if (empty($policy['delivery']) && (empty($group->zoneCodes()) || array_diff($group->zoneCodes(), array_column($policy['destination_rules'], 'destination_zone_code')))) {
                $this->reject('servicecatalog.default_commitment_required_for_uncovered_zones');
            }
        }

        return $policy;
    }

    public static function fromBinding(array $b): array
    {
        $base = [
            'mode' => 'NONE',
            'anchor' => 'CONSIGNMENT_CREATED',
            'calculation' => 'ELAPSED',
            'duration_value' => 1,
            'duration_unit' => 'HOUR',
            'day_offset' => 0,
            'window_codes' => [],
        ];

        return [
            'include_holidays' => true,
            'zone_set_id' => null,
            'destination_rules' => [],
            'pickup' => [...$base, 'mode' => $b['pickup_mode'] ?? 'SELECTABLE_WINDOW'],
            'delivery' => [
                ...$base,
                'mode' => $b['delivery_mode'] ?? 'COMPUTED',
                'anchor' => $b['duration_anchor'] ?? 'PICKUP_COMMITMENT_END',
                'duration_value' => (int) ($b['duration_value'] ?? 72),
                'duration_unit' => $b['duration_unit'] ?? 'HOUR',
            ],
        ];
    }

    private function reject(string $messageKey): never
    {
        throw new ApiException(ApiErrorCode::ValidationError, 422, $messageKey);
    }
}
