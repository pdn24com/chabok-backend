<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

interface NetworkChangeRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $correlationId, AreaRecord|NodeRecord|null $before, AreaRecord|NodeRecord $after): void;
}
