<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Operations\Application\Contracts\DeliveryNodeCapabilitiesInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class DeliveryNodeCapabilities implements DeliveryNodeCapabilitiesInterface
{
    public function __construct(
        private NodeRepositoryInterface $nodeRepository,
    ) {}

    public function nodeHasCapability(
        string $hqId,
        string $nodeId,
        string $capability,
    ): bool {
        $node = $this->nodeRepository->findActive($hqId, $nodeId);
        if ($node === null) {
            return false;
        }
        $capabilities = $node->capabilities ?? [];

        return in_array($capability, is_array($capabilities) ? $capabilities : [], true);
    }
}
