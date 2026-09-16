<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

final readonly class DeliveryNodeCapabilities
{
    public function __construct(private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks)
    {
    }

    public function nodeHasCapability(string $hqId, string $nodeId, string $capability): bool
    {
        $node = $this->tasks->activeNode($hqId, $nodeId);
        if ($node === null) {
            return false;
        }
        $capabilities = json_decode((string) ($node->capabilities ?? '[]'), true);
        return in_array($capability, is_array($capabilities) ? $capabilities : [], true);
    }
}
