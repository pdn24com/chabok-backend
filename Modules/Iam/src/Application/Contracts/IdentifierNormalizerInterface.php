<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

use Modules\Iam\Domain\ValueObjects\NormalizedIdentifiers;

interface IdentifierNormalizerInterface
{
    public function username(?string $value): ?string;

    public function email(?string $value): ?string;

    public function mobile(?string $value): ?string;

    public function identifiers(?string $username, ?string $mobile, ?string $email): NormalizedIdentifiers;
}
