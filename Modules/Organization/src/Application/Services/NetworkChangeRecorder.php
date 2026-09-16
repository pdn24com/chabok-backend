<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class NetworkChangeRecorder
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function record(
        AuthenticatedPrincipal $actor,
        string $action,
        string $type,
        string $id,
        string $correlationId,
        ?array $before,
        array $after,
    ): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, $before, $after);
        $this->outbox->write($actor->hqId, $type, $id, 'network.configuration.changed', $correlationId, ['action' => $action, 'target_type' => $type, 'target_id' => $id]);
    }
}
