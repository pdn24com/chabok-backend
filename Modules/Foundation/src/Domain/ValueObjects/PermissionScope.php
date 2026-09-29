<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

use Modules\Foundation\Domain\Enums\ScopeType;

final readonly class PermissionScope
{
    public function __construct(
        public ScopeType $type,
        public ?string $id,
        public bool $includesDescendants = false,
    ) {}
}
