<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

/** Explicit commercial data. Numeric wire values and field presence are retained for quote matching.
 * @param  list<string>  $presentFields
 */
final class ConsignmentParcelDto
{
    public function __construct(
        public ?string $parcelId = null,
        public ?string $contentDescription = null,
        public int|float|string|null $weightKg = null,
        public int|float|string|null $widthCm = null,
        public int|float|string|null $lengthCm = null,
        public int|float|string|null $heightCm = null,
        public array $presentFields = [],
    ) {}
}
