<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

/** Explicit commercial data. Numeric wire values and field presence are retained for quote matching.
 * @param  list<string>  $presentFields
 */
final class ConsignmentContactDto
{
    public function __construct(
        public ?string $addressBookEntryId = null,
        public ?string $contactName = null,
        public ?string $mobile = null,
        public ?string $phone = null,
        public ?string $addressText = null,
        public ?string $country = null,
        public ?string $state = null,
        public ?string $city = null,
        public ?string $cityId = null,
        public ?string $provinceId = null,
        public ?string $legacyCityCode = null,
        public ?string $postalCode = null,
        public int|float|string|null $latitude = null,
        public int|float|string|null $longitude = null,
        public ?array $cityReference = null,
        public array $presentFields = [],
    ) {}
}
