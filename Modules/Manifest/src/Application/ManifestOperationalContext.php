<?php

declare(strict_types=1);

namespace Modules\Manifest\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestOperationalContext
{
    public function __construct(
        private \Modules\Manifest\Application\UseCases\GetAvailableManifestContexts\GetAvailableManifestContextsHandler $getAvailableManifestContexts,
        private \Modules\Manifest\Application\UseCases\NormalizeManifestContext\NormalizeManifestContextHandler $normalizeManifestContext,
        private \Modules\Manifest\Application\Services\ManifestContextProjection $manifestContextProjection,
    )
    {
    }

    public function options(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->getAvailableManifestContexts->handle(new \Modules\Manifest\Application\UseCases\GetAvailableManifestContexts\GetAvailableManifestContextsCommand($actor, $nodeId))->data;
    }

    public function normalize(AuthenticatedPrincipal $actor, string $nodeId, array $input): array
    {
        return $this->normalizeManifestContext->handle(new \Modules\Manifest\Application\UseCases\NormalizeManifestContext\NormalizeManifestContextCommand($actor, $nodeId, $input))->data;
    }

    public function summaries(string $hq, array $manifests): array
    {
        return $this->manifestContextProjection->summaries($hq, $manifests);
    }

    public function summary(object $manifest, ?array $references = null): array
    {
        return $this->manifestContextProjection->summary($manifest, $references);
    }
}
