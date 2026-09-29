<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\CreateUser;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Dto\UserCreationDto;

final readonly class CreateUserCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public UserCreationDto $input,
        public string $correlationId,
    ) {}
}
