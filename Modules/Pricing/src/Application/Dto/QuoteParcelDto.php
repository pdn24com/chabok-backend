<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

/**
 * Typed request values. Numeric strings retain their wire representation for stable fingerprints.
 * presentFields preserves omitted versus null; extensions are opaque transport metadata only.
 */
final class QuoteParcelDto
{
    /** @param list<string> $presentFields @param array<string, mixed> $extensions */
    public function __construct(
        public ?string $contentDescription = null,
        public int|float|string|null $weightKg = null,
        public int|float|string|null $lengthCm = null,
        public int|float|string|null $widthCm = null,
        public int|float|string|null $heightCm = null,
        public array $presentFields = [],
        public array $extensions = [],
    ) {}
}
