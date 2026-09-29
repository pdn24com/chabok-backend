<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerHistory;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCustomerHistoryCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $customerId) {}
}
