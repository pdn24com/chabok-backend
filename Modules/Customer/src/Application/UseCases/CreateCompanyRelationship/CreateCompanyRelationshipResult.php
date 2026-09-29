<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCompanyRelationship;

use Modules\Customer\Application\Dto\CustomerRelationshipDto;

/** The relationship as it was stored, with both ends and the post named. */
final readonly class CreateCompanyRelationshipResult
{
    public function __construct(
        public CustomerRelationshipDto $relationship,
    ) {}
}
