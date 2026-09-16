<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface IdentifierGenerator
{
    public function uuid(): string;
}
