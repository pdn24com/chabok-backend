<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final class QuotedDeliveryWindowDto
{
    public function __construct(
        public ?string $gregorianDate = null,
        public ?string $jalaliDisplayDate = null,
        public ?string $persianWeekdayLabel = null,
        public ?string $persianMonthLabel = null,
        public array $timeRanges = [],
        public array $presentFields = [],
    ) {}
}
