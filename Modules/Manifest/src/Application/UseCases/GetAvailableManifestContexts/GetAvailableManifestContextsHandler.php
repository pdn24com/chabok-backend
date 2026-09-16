<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetAvailableManifestContexts;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetAvailableManifestContextsHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestContextReferences $manifestContextReferences,
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Manifest\Application\Services\ManifestContextAccess $manifestContextAccess,
        private \Modules\Manifest\Application\Services\ManifestContextOptions $manifestContextOptions,
        private \Modules\Manifest\Domain\ManifestContextShape $manifestContextShape,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
    )
    {
    }

    public function handle(GetAvailableManifestContextsCommand $command): GetAvailableManifestContextsResult
    {
        return new GetAvailableManifestContextsResult($this->execute($command->actor, $command->nodeId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        $node = $this->manifestContextReferences->node((string) $actor->hqId, $nodeId);
        $authorization = $this->authorization->resolve($actor);
        $accessibleNodeIds = $this->manifestContextAccess->accessibleNodeIds($actor);
        $contexts = array_map(function (array $option): array {
            unset($option['resolution']);
            return $option;
        }, $this->manifestContextOptions->operationalOptions($actor, $nodeId, $node));
        return [
            'current_node' => $this->manifestContextShape->nodeResource($node),
            'target_nodes' => $this->manifestContextReferences->targetNodes((string) $actor->hqId, $accessibleNodeIds),
            'transition_contracts' => $this->manifestContextShape->transitionContracts(),
            'contexts' => $contexts,
            'drivers' => in_array('driver.view', $authorization['permissions'] ?? [], true) ? $this->manifestContextReferences->drivers((string) $actor->hqId, $this->scopedAccess->nodes($authorization, 'driver.view')) : [],
            'vehicles' => $this->manifestContextReferences->vehicles((string) $actor->hqId, $nodeId),
        ];
    }
}
