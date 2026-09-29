<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\CoverageChangeRecorderInterface;

final readonly class CoverageChangeRecorder implements CoverageChangeRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        string $action,
        string $type,
        string $id,
        string $status,
        string $correlationId,
        ?string $note = null,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, safeNote: $note, sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, $type, $id, 'network.configuration.changed', $correlationId, [
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
            'status' => $status,
        ]);
    }
}
