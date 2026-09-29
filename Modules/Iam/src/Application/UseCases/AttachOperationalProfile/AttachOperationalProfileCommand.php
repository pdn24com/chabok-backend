<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\AttachOperationalProfile;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Dto\OperationalProfileInputDto;

final readonly class AttachOperationalProfileCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public OperationalProfileInputDto $input,
        public string $correlationId,
    ) {}
}
