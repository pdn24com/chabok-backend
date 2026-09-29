<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerContactPoints;

use Modules\Customer\Application\Dto\CustomerContactPointDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class SaveCustomerContactPointsCommand
{
    /**
     * @param  list<CustomerContactPointDraftDto>  $items  the complete set the person should hold afterwards
     */
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public array $items,
    ) {}
}
