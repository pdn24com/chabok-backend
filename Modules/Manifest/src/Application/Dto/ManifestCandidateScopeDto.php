<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

final readonly class ManifestCandidateScopeDto
{
    /** @param list<string> $sourceStatuses */
    public function __construct(
        public array $sourceStatuses,
        public bool $requiresSourceManifest,
        public ?string $sourceManifestId,
        public ?string $custodyType,
        public ?string $nodeId,
    ) {}
}
