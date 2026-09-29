<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

final readonly class ManifestCountsDto
{
    public function __construct(public int $pending, public int $validated, public int $succeeded, public int $failed, public int $skipped) {}

    /** @param array<string, int|string> $counts Grouped SQL status counts. */
    public static function fromStatusCounts(array $counts): self
    {
        return new self((int) ($counts['PENDING'] ?? 0), (int) ($counts['VALIDATED'] ?? 0), (int) ($counts['SUCCEEDED'] ?? 0), (int) ($counts['FAILED'] ?? 0), (int) ($counts['SKIPPED'] ?? 0));
    }

    public function total(): int
    {
        return $this->pending + $this->validated + $this->succeeded + $this->failed + $this->skipped;
    }
}
