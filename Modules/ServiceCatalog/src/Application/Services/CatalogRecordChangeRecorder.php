<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CatalogRecordChangeRecorder
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function record(
        AuthenticatedPrincipal $actor,
        string $id,
        string $correlation,
        string $action,
        ?array $before,
        array $after,
    ): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, 'SERVICE_CATALOG_RECORD', $id, $correlation, before: $before, after: $after, sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, 'SERVICE_CATALOG_RECORD', $id, 'service.catalog.changed', $correlation, ['action' => $action, 'target_type' => 'SERVICE_CATALOG_RECORD', 'target_id' => $id]);
    }
}
