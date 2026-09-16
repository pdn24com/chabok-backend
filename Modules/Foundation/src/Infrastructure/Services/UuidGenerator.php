<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Services;

use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;

final class UuidGenerator implements IdentifierGenerator
{
    public function uuid(): string
    {
        return (string) Str::uuid();
    }
}
