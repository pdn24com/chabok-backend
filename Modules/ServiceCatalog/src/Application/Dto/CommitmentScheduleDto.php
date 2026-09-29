<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Modules\ServiceCatalog\Application\Mappers\CommitmentInput;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentWindow;

final class CommitmentScheduleDto
{
    /** @param list<CommitmentWindow> $windows
     * @param  list<ScheduleScopeDto>  $scopes
     */
    public function __construct(
        public ?string $title,
        public ?string $code,
        public string $timezone,
        public string $calendarCode,
        public ?string $validFrom,
        public ?string $validTo,
        public array $windows,
        public array $scopes,
        public ?array $commitmentPolicy,
        public ?int $expectedVersion,
        public array $presentFields = [],
        public ?string $sourceFingerprint = null,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            title: $input['title'] ?? null,
            code: $input['code'] ?? null,
            timezone: $input['timezone'] ?? 'Asia/Tehran',
            calendarCode: $input['calendar_code'] ?? 'IR_STANDARD',
            validFrom: $input['valid_from'] ?? null,
            validTo: $input['valid_to'] ?? null,
            windows: array_map(CommitmentInput::window(...), $input['windows'] ?? []),
            scopes: array_map(ScheduleScopeDto::fromValidated(...), $input['scopes'] ?? [['scope_type' => 'HQ']]),
            commitmentPolicy: $input['commitment_policy'] ?? null,
            expectedVersion: isset($input['expected_version']) ? (int) $input['expected_version'] : null,
            presentFields: array_keys($input),
        );
    }
}
