<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\FleetChangeRecorderInterface;
use Modules\Operations\Application\Serialization\FleetDocument;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

final readonly class FleetChangeRecorder implements FleetChangeRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        string $action,
        string $type,
        string $id,
        string $status,
        string $correlationId,
        DriverRecord|VehicleRecord|null $before,
        DriverRecord|VehicleRecord $after,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, before: $before === null ? null : ($before instanceof DriverRecord ? FleetDocument::driver($before) : FleetDocument::vehicle($before)), after: $after instanceof DriverRecord ? FleetDocument::driver($after) : FleetDocument::vehicle($after), sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, $type, $id, 'fleet.configuration.changed', $correlationId, [
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
            'status' => $status,
        ]);
    }
}
