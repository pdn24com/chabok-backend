<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\GetOwnProfile;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetOwnProfileCommand
{
    public function __construct(public AuthenticatedPrincipal $actor) {}
}
