<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Application\Data\Page;
use DateTimeInterface;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CoveragePolicyService
{
    public function __construct(
        private \Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesHandler $listCoveragePolicies,
        private \Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyHandler $createCoveragePolicy,
        private \Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyHandler $getCoveragePolicy,
        private \Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsHandler $listCoverageVersions,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coveragePolicyReader,
        private \Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionHandler $createCoverageVersion,
        private \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler $getCoverageVersion,
        private \Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionHandler $updateCoverageVersion,
        private \Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionHandler $transitionCoverageVersion,
        private \Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyHandler $resolveCoveragePolicy,
    )
    {
    }

    public function list(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listCoveragePolicies->handle(new \Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesCommand($actor, $filters))->data;
    }

    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createCoveragePolicy->handle(new \Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyCommand($actor, $input, $correlationId))->data;
    }

    public function policy(AuthenticatedPrincipal $actor, string $id): array
    {
        return $this->getCoveragePolicy->handle(new \Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyCommand($actor, $id))->data;
    }

    public function history(AuthenticatedPrincipal $actor, string $policyId, int $page = 1, int $perPage = 20): Page
    {
        return $this->listCoverageVersions->handle(new \Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsCommand($actor, $policyId, $page, $perPage))->data;
    }

    public function presentVersion(object $row): array
    {
        return $this->coveragePolicyReader->presentVersion($row);
    }

    public function createVersion(AuthenticatedPrincipal $actor, string $policyId, array $input, string $correlationId): array
    {
        return $this->createCoverageVersion->handle(new \Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionCommand($actor, $policyId, $input, $correlationId))->data;
    }

    public function version(AuthenticatedPrincipal $actor, string $policyId, string $versionId): array
    {
        return $this->getCoverageVersion->handle(new \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand($actor, $policyId, $versionId))->data;
    }

    public function update(
        AuthenticatedPrincipal $actor,
        string $policyId,
        string $versionId,
        array $input,
        string $correlationId,
    ): array
    {
        return $this->updateCoverageVersion->handle(new \Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionCommand($actor, $policyId, $versionId, $input, $correlationId))->data;
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $policyId,
        string $versionId,
        string $action,
        int $expected,
        ?string $note,
        string $correlationId,
    ): array
    {
        return $this->transitionCoverageVersion->handle(new \Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionCommand($actor, $policyId, $versionId, $action, $expected, $note, $correlationId))->data;
    }

    public function resolve(
        string $hqId,
        string $target,
        array $input,
        ?string $offeringVersionId = null,
        ?DateTimeInterface $at = null,
    ): array
    {
        return $this->resolveCoveragePolicy->handle(new \Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyCommand($hqId, $target, $input, $offeringVersionId, $at))->data;
    }
}
