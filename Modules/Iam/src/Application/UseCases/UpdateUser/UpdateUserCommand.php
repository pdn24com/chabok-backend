<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\UpdateUser;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Dto\ProfileChangesDto;

final readonly class UpdateUserCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public ProfileChangesDto $input,
        public string $correlationId,
    ) {}
}
