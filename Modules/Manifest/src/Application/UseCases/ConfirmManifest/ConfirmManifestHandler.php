<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ConfirmManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConfirmManifestHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Manifest\Application\ManifestOrchestrationService $orchestration,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
    )
    {
    }

    public function handle(ConfirmManifestCommand $command): ConfirmManifestResult
    {
        return new ConfirmManifestResult($this->execute($command->actor, $command->nodeId, $command->id, $command->expected, $command->correlationId, $command->reasonCode, $command->description));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $correlationId,
        ?string $reasonCode = null,
        ?string $description = null,
    ): array
    {
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.approve');
        $this->orchestration->confirm($actor, $nodeId, $id, $expected, $correlationId, $reasonCode, $description);
        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
