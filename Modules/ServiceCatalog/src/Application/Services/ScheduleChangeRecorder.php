<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChangeRecorderInterface;

final readonly class ScheduleChangeRecorder implements ScheduleChangeRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        string $action,
        string $id,
        string $correlationId,
        array $after,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $action, 'COMMITMENT_SCHEDULE', $id, $correlationId, after: $after, sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, 'COMMITMENT_SCHEDULE', $id, 'service.catalog.commitment-schedule.changed', $correlationId, ['action' => $action, 'target_id' => $id]);
    }
}
