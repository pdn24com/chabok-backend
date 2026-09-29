<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Adapters;

use Modules\Foundation\Application\Contracts\IdentifierGeneratorInterface;

final class RandomTokenGenerator implements IdentifierGeneratorInterface
{
    public function token(): string
    {
        return bin2hex(random_bytes(16));
    }
}
