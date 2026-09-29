<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\GetUser;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetUserCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $userId) {}
}
