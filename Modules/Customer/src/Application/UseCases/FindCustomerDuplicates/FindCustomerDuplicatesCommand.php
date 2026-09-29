<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\FindCustomerDuplicates;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class FindCustomerDuplicatesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public ?string $mobile, public ?string $email) {}
}
