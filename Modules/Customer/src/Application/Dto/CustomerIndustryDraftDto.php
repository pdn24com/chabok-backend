<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

/** One industry of the set a customer's industry picker submits. */
final readonly class CustomerIndustryDraftDto
{
    public function __construct(public string $industryId, public bool $isPrimary) {}
}
