<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface CoveragePolicyRepository
{
    public function policies(string $hq, array $filters): Page;

    public function codeExists(string $hq, string $code): bool;

    public function policy(string $hq, string $id): ?object;

    public function policyExists(string $hq, string $policyId): bool;

    public function history(string $hq, string $policyId, int $page, int $perPage): Page;

    public function lastVersionNumber(string $policyId): int;

    public function insertPolicy(array $attributes): void;

    public function insertVersion(array $attributes): void;

    public function insertRule(array $attributes): void;

    public function deleteRules(string $versionId): void;

    public function updateVersion(string $versionId, array $changes): void;

    public function publishPolicy(string $policyId, string $versionId, \DateTimeInterface $at): void;

    public function supersedePolicy(string $policyId, string $versionId, \DateTimeInterface $at): void;

    public function matchingRules(string $hqId, string $target, ?string $offeringVersionId, \DateTimeInterface $at): array;

    public function offeringVisible(string $hq, string $offeringVersionId): bool;

    public function activeNode(string $hq, string $nodeId): bool;

    public function versionExists(string $hq, string $versionId): bool;

    public function version(string $hq, string $policy, string $version): ?object;

    public function lockVersion(string $hq, string $policy, string $version): ?object;

    public function provinceExists(string $id): bool;

    public function cityExists(string $id): bool;

    public function rulesInCreationOrder(string $versionId): array;

    public function rulesByPriority(string $versionId): array;

    public function publishedDatesOverlap(string $hq, string $policyId, string $versionId, mixed $effectiveFrom, mixed $effectiveTo): bool;
}
