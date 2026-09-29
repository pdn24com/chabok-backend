<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface RouteChangeRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $status, string $correlationId, ?string $note = null): void;
}
