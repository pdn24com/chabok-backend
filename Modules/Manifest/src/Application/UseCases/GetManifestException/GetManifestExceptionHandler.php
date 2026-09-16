<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestException;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetManifestExceptionHandler
{
    public function __construct(private \Modules\Manifest\Application\ManifestOrchestrationService $orchestration)
    {
    }

    public function handle(GetManifestExceptionCommand $command): GetManifestExceptionResult
    {
        return new GetManifestExceptionResult($this->execute($command->actor, $command->nodeId, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->orchestration->exceptionState($actor, $nodeId, $id);
    }
}
