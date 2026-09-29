<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Application\Contracts\CommitmentSettingsInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleInputInterface;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;

final readonly class ScheduleInput implements ScheduleInputInterface
{
    public function __construct(private CommitmentSettingsInterface $commitmentSettings) {}

    public function versionColumns(CommitmentScheduleDto $input): array
    {
        $extra = [];
        if ($input->commitmentPolicy !== null) {
            $extra['commitment_policy'] = $input->commitmentPolicy;
        }

        return [
            ...$extra,
            'timezone' => $input->commitmentPolicy !== null ? $this->commitmentSettings->timezone() : $input->timezone,
            'calendar_code' => $input->calendarCode,
            'valid_from' => $this->databaseTimestamp($input->validFrom),
            'valid_to' => $this->databaseTimestamp($input->validTo),
        ];
    }

    public function databaseTimestamp(?string $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }
}
