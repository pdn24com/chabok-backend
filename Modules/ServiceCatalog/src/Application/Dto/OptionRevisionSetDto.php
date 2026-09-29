<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class OptionRevisionSetDto
{
    /** @param list<string> $versionIds */
    public function __construct(
        public string $reference,
        public ?string $optionId,
        public array $versionIds,
        public ?string $currentVersionId,
    ) {}
}
