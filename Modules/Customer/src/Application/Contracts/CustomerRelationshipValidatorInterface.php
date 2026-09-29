<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Customer\Application\Dto\CompanyRelationshipDraftDto;
use Modules\Customer\Domain\ValueObjects\MobileNumber;

interface CustomerRelationshipValidatorInterface
{
    /**
     * Judges a relationship about to be created for one company: the validity window, a primary flag that
     * is not put on an already ended relationship, a post that belongs to the company, an existing person
     * that really is a PERSON of the tenant and does not already hold an open relationship with the company,
     * and, for a new person, a usable mobile number that no other person owns. Returns the normalised
     * mobile of a new person, or null when the person already exists. It must run inside the transaction
     * that writes the relationship and that has already locked the company's row.
     *
     * @param  string  $today  the current calendar day, Y-m-d
     */
    public function validate(string $hqId, string $companyCustomerId, CompanyRelationshipDraftDto $draft, string $today): ?MobileNumber;
}
