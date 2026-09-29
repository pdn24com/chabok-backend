<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\UpdateOwnProfile;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Dto\ProfileChangesDto;

final readonly class UpdateOwnProfileCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public ProfileChangesDto $input,
        public string $correlationId,
    ) {}
}
