<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Customer\Application\Dto\CustomerDraftDto;
use Modules\Customer\Domain\ValueObjects\MobileNumber;

interface CustomerDraftValidatorInterface
{
    /**
     * Validates the tenant-scoped references a draft points at and returns the accepted mobile number,
     * so the caller writes the canonical form without parsing the operator input a second time.
     */
    public function validate(string $hqId, CustomerDraftDto $draft): MobileNumber;
}
