<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Contracts\NetworkChangeRecorderInterface;
use Modules\Organization\Application\Serialization\NetworkDocument;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class NetworkChangeRecorder implements NetworkChangeRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        string $action,
        string $type,
        string $id,
        string $correlationId,
        AreaRecord|NodeRecord|null $before,
        AreaRecord|NodeRecord $after,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, $before === null ? null : NetworkDocument::serialize($before), NetworkDocument::serialize($after));
        $this->outboxWriter->write($actor->hqId, $type, $id, 'network.configuration.changed', $correlationId, [
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
        ]);
    }
}
