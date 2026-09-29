<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final class OfferingOptionRuleDto
{
    public function __construct(
        public string $serviceOptionVersionId = '',
        public string $compatibility = '',
        public ?array $condition = null,
        /** @var list<string> */ public array $presentFields = [],
    ) {}
}
