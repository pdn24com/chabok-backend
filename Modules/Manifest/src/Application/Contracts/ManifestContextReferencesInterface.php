<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

interface ManifestContextReferencesInterface
{
    public function drivers(string $hq, array $accessibleNodeIds): array;

    public function vehicles(string $hq, string $node): array;

    public function targetNodes(string $hq, array $accessibleNodeIds): array;

    public function node(string $hq, string $node): NodeRecord;

    public function nullableNode(string $hq, ?string $id): ?array;

    public function routePlan(string $hq, ?string $id): ?array;

    public function routeLeg(string $hq, ?string $id): ?array;

    public function driver(string $hq, ?string $id): ?array;

    public function vehicle(string $hq, ?string $id): ?array;
}
