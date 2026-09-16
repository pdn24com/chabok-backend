<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver;

final readonly class SchedulePolicy
{
    public function __construct(private CommitmentZoneResolver $zones, private Contracts\ScheduleInputValidator $inputValidator)
    {
    }

    public function validate(array $policy, array $windows, string $hqId): array
    {
        $this->inputValidator->validate($policy);
        if ($policy['pickup']['anchor'] !== 'CONSIGNMENT_CREATED') {
            $this->reject('مبدأ جمع‌آوری باید پذیرش مرسوله باشد.');
        }
        $policies = [['PICKUP', $policy['pickup']]];
        if (!empty($policy['delivery'])) {
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
                $available = array_column(array_filter($windows, fn($w) => $w['window_type'] === $kind && ($w['active'] ?? true)), 'window_code');
                if (!$available || array_diff($p['window_codes'], $available)) {
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
            $this->reject('یک تعهد پیش‌فرض تعریف کنید یا گروه زون و تعهد تمام مناطق آن را مشخص کنید.');
        }
        if (!empty($policy['zone_set_id'])) {
            $group = $this->zones->group($hqId, $policy['zone_set_id']);
            foreach ($policy['destination_rules'] as $rule) {
                if (!in_array($rule['destination_zone_code'], $group['zone_codes'], true)) {
                    $this->reject('زون مقصد در نسخه جاری گروه زون وجود ندارد.');
                }
            }
            if (empty($policy['delivery']) && (empty($group['zone_codes']) || array_diff($group['zone_codes'], array_column($policy['destination_rules'], 'destination_zone_code')))) {
                $this->reject('برای مناطق بدون تعهد اختصاصی، ابتدا یک تعهد پیش‌فرض تعریف کنید.');
            }
        }
        return $policy;
    }

    private function reject(string $message): never
    {
        throw new ApiException(ApiErrorCode::ValidationError, 422, $message);
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
}
