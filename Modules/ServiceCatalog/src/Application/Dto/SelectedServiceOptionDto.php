<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class SelectedServiceOptionDto
{
    /** @param array<string, string> $labels @param array<string, mixed> $definition Catalog-defined JSON, not an application data envelope. */
    public function __construct(
        public string $optionId,
        public string $versionId,
        public string $code,
        public array $labels,
        public array $definition,
        public string $compatibility,
        public bool $required,
        public bool $selectable,
        public ?string $reasonCode,
    ) {}
}
