<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestContextOptions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetManifestContextOptionsHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Manifest\Application\ManifestOperationalContext $operationalContext,
    )
    {
    }

    public function handle(GetManifestContextOptionsCommand $command): GetManifestContextOptionsResult
    {
        return new GetManifestContextOptionsResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view');
        return $this->operationalContext->options($actor, $nodeId);
    }
}
