<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;

final readonly class PricingChangeRecorder implements PricingChangeRecorderInterface
{
    public function __construct(private AuditWriterInterface $auditWriter, private OutboxWriterInterface $outboxWriter) {}

    public function record(
        AuthenticatedPrincipal $actor,
        string $action,
        string $type,
        string $id,
        string $correlationId,
        array $after,
    ): void {
        $this->auditWriter->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, after: $after, sourceClient: SourceClient::BranchPanel->value);
        $this->outboxWriter->write($actor->hqId, $type, $id, 'pricing.configuration.changed', $correlationId, [
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
        ]);
    }
}
