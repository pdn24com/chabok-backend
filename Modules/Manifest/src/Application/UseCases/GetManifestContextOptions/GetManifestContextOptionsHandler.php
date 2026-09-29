<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestContextOptions;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestContextAccessInterface;
use Modules\Manifest\Application\Contracts\ManifestContextOptionsInterface;
use Modules\Manifest\Application\Contracts\ManifestContextReferencesInterface;
use Modules\Manifest\Application\Serialization\ManifestContextDocument;

final readonly class GetManifestContextOptionsHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestContextReferencesInterface $manifestContextReferences,
        private AccessContextResolverInterface $accessContextResolver,
        private ManifestContextAccessInterface $manifestContextAccess,
        private ManifestContextOptionsInterface $manifestContextOptions,
        private ManifestContextDocument $manifestContextDocument,
        private ScopedAccessInterface $scopedAccess,
    ) {}

    public function handle(GetManifestContextOptionsCommand $command): array
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view');
        $node = $this->manifestContextReferences->node((string) $actor->hqId, $nodeId);
        $authorization = $this->accessContextResolver->resolve($actor);
        $accessibleNodeIds = $this->manifestContextAccess->accessibleNodeIds($actor);
        $contexts = array_map($this->manifestContextDocument->optionResource(...), $this->manifestContextOptions->operationalOptions($actor, $nodeId, $node));

        return [
            'current_node' => $this->manifestContextDocument->nodeResource($node),
            'target_nodes' => $this->manifestContextReferences->targetNodes((string) $actor->hqId, $accessibleNodeIds),
            'transition_contracts' => $this->manifestContextDocument->transitionContracts(),
            'contexts' => $contexts,
            'drivers' => $authorization->hasPermission('driver.view') ? $this->manifestContextReferences->drivers((string) $actor->hqId, $this->scopedAccess->nodes($authorization, 'driver.view')) : [],
            'vehicles' => $this->manifestContextReferences->vehicles((string) $actor->hqId, $nodeId),
        ];
    }
}
