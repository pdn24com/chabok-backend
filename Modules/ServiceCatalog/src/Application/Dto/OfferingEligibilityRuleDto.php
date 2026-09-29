<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final class OfferingEligibilityRuleDto
{
    public function __construct(
        public string $dimension = '',
        public string $factKey = '',
        public string $operator = 'EQ',
        public array|string|int|float|bool|null $expectedValue = null,
        public string $reasonCode = '',
        public int $priority = 100,
        /** @var list<string> */ public array $presentFields = [],
    ) {}
}
