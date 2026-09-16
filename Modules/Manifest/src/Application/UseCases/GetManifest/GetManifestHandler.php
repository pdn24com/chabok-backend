<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetManifestHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
    )
    {
    }

    public function handle(GetManifestCommand $command): GetManifestResult
    {
        return new GetManifestResult($this->execute($command->actor, $command->nodeId, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id): array
    {
        return $this->manifestReader->detail($actor, $nodeId, $id, $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view'));
    }
}
