<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogRecordDetailDto;
use Modules\ServiceCatalog\Application\Serialization\CatalogRecordDocument;

final readonly class CatalogRecordChangeRecorder implements CatalogRecordChangeRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        string $id,
        string $correlation,
        string $action,
        ?CatalogRecordDetailDto $before,
        CatalogRecordDetailDto $after,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $action, 'SERVICE_CATALOG_RECORD', $id, $correlation, before: $before === null ? null : CatalogRecordDocument::make($before), after: CatalogRecordDocument::make($after), sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, 'SERVICE_CATALOG_RECORD', $id, 'service.catalog.changed', $correlation, [
            'action' => $action,
            'target_type' => 'SERVICE_CATALOG_RECORD',
            'target_id' => $id,
        ]);
    }
}
