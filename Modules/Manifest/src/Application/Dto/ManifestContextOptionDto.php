<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final class ManifestContextOptionDto
{
    public function __construct(
        public readonly string $status,
        public readonly ManifestContextType $type,
        public readonly string $key,
        public readonly string $label,
        public readonly ManifestSelectionDto $selection,
        public ?NodeRecord $relatedNode = null,
        public ?NodeRecord $targetNode = null,
        public string $relatedNodeRole = 'COUNTERPARTY',
    ) {}
}
