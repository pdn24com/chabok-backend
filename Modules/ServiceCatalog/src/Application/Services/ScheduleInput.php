<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;

final readonly class ScheduleInput
{
    public function __construct(private \Modules\ServiceCatalog\Application\Contracts\CommitmentSettings $settings)
    {
    }

    public function versionColumns(array $input): array
    {
        $extra = [];
        if (isset($input['commitment_policy'])) {
            $extra['commitment_policy'] = json_encode($input['commitment_policy'], JSON_THROW_ON_ERROR);
            $input['timezone'] = $this->settings->timezone();
        }
        return [
            ...$extra,
            'timezone' => $input['timezone'] ?? 'Asia/Tehran',
            'calendar_code' => $input['calendar_code'] ?? 'IR_STANDARD',
            'valid_from' => $this->databaseTimestamp($input['valid_from'] ?? null),
            'valid_to' => $this->databaseTimestamp($input['valid_to'] ?? null),
        ];
    }

    public function databaseTimestamp(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }
}
