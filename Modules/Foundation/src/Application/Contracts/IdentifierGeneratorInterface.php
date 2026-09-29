<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface IdentifierGeneratorInterface
{
    public function token(): string;
}
