<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ApproveManifestException;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ApproveManifestExceptionHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Manifest\Application\ManifestOrchestrationService $orchestration,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
    )
    {
    }

    public function handle(ApproveManifestExceptionCommand $command): ApproveManifestExceptionResult
    {
        return new ApproveManifestExceptionResult($this->execute($command->actor, $command->nodeId, $command->id, $command->manifestVersion, $command->exceptionVersion, $command->reason, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        ?string $reason,
        string $correlationId,
    ): array
    {
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.approve');
        $this->orchestration->approve($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $reason, $correlationId);
        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
