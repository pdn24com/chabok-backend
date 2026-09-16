<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CatalogChangeRecorder
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
        array $after,
    ): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, after: $after, sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, $type, $id, 'service.catalog.changed', $correlationId, ['action' => $action, 'target_type' => $type, 'target_id' => $id]);
    }
}
