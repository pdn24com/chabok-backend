<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Modules\Consignment\Domain\Enums\ConsignmentStatusGroup;
use Modules\Consignment\Domain\Enums\OperationalStatusScope;
use Modules\Consignment\Domain\Enums\OperationalStatusTone;

final readonly class OperationalStatusDto
{
    public function __construct(
        public ?string $code,
        public OperationalStatusScope $scope,
        public ?int $expectedVersion,
        public string $titleFa,
        public ?string $titleEn,
        public bool $titleEnProvided,
        public ?string $partialTitleFa,
        public bool $partialTitleFaProvided,
        public ?string $partialTitleEn,
        public bool $partialTitleEnProvided,
        public OperationalStatusTone $tone,
        public ?ConsignmentStatusGroup $statusGroup,
        public bool $statusGroupProvided,
        public bool $isTerminal,
        public bool $isActive,
        public int $sortOrder,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            code: $input['code'] ?? null,
            scope: OperationalStatusScope::from($input['scope'] ?? 'TENANT'),
            expectedVersion: isset($input['expected_version']) ? (int) $input['expected_version'] : null,
            titleFa: $input['title_fa'],
            titleEn: $input['title_en'] ?? null, titleEnProvided: array_key_exists('title_en', $input),
            partialTitleFa: $input['partial_title_fa'] ?? null, partialTitleFaProvided: array_key_exists('partial_title_fa', $input),
            partialTitleEn: $input['partial_title_en'] ?? null, partialTitleEnProvided: array_key_exists('partial_title_en', $input),
            tone: OperationalStatusTone::from($input['tone']),
            statusGroup: isset($input['status_group']) ? ConsignmentStatusGroup::from($input['status_group']) : null,
            statusGroupProvided: array_key_exists('status_group', $input),
            isTerminal: (bool) $input['is_terminal'], isActive: (bool) $input['is_active'], sortOrder: (int) $input['sort_order'],
        );
    }

    /** Attributes at the model persistence boundary; omitted nullable fields remain unchanged. */
    public function attributes(): array
    {
        return [
            'title_fa' => $this->titleFa, 'tone' => $this->tone, 'is_terminal' => $this->isTerminal,
            'is_active' => $this->isActive, 'sort_order' => $this->sortOrder,
            ...($this->titleEnProvided ? ['title_en' => $this->titleEn] : []),
            ...($this->partialTitleFaProvided ? ['partial_title_fa' => $this->partialTitleFa] : []),
            ...($this->partialTitleEnProvided ? ['partial_title_en' => $this->partialTitleEn] : []),
            ...($this->statusGroupProvided ? ['status_group' => $this->statusGroup] : []),
        ];
    }
}
