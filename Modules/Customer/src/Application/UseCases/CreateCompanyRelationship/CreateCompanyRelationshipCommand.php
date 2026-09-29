<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCompanyRelationship;

use Modules\Customer\Application\Dto\CompanyRelationshipDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCompanyRelationshipCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public CompanyRelationshipDraftDto $relationship,
    ) {}
}
