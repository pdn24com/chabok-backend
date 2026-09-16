<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ResubmitManifestException;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ResubmitManifestExceptionHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Manifest\Application\ManifestOrchestrationService $orchestration,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
    )
    {
    }

    public function handle(ResubmitManifestExceptionCommand $command): ResubmitManifestExceptionResult
    {
        return new ResubmitManifestExceptionResult($this->execute($command->actor, $command->nodeId, $command->id, $command->manifestVersion, $command->exceptionVersion, $command->code, $command->description, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $manifestVersion,
        int $exceptionVersion,
        string $code,
        string $description,
        string $correlationId,
    ): array
    {
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.edit');
        $this->orchestration->resubmit($actor, $nodeId, $id, $manifestVersion, $exceptionVersion, $code, $description, $correlationId);
        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
