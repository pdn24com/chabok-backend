<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

interface ManifestNumberAllocatorInterface
{
    public function next(): string;
}
