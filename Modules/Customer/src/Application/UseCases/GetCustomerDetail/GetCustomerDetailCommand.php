<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerDetail;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCustomerDetailCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
