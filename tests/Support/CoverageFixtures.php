<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\CoveragePolicyDto;
use Modules\Operations\Application\Dto\CoveragePolicyFiltersDto;
use Modules\Operations\Application\Dto\CoverageVersionChangesDto;
use Modules\Operations\Application\Dto\CoverageVersionDto;
use Modules\Operations\Application\Mappers\CoverageInput;
use Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\CreateCoveragePolicy\CreateCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionCommand;
use Modules\Operations\Application\UseCases\CreateCoverageVersion\CreateCoverageVersionHandler;
use Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler;
use Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesCommand;
use Modules\Operations\Application\UseCases\ListCoveragePolicies\ListCoveragePoliciesHandler;
use Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsCommand;
use Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsHandler;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyHandler;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyResult;
use Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionCommand;
/** Integration fixture convenience; production invokes focused handlers. */
use Modules\Operations\Application\UseCases\TransitionCoverageVersion\TransitionCoverageVersionHandler;
use Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionCommand;
use Modules\Operations\Application\UseCases\UpdateCoverageVersion\UpdateCoverageVersionHandler;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;
use Modules\Operations\Presentation\Http\Resources\CoveragePolicyResource;
use Modules\Operations\Presentation\Http\Resources\CoverageVersionResource;

final readonly class CoverageFixtures
{
    public function __construct(
        private ListCoveragePoliciesHandler $listCoveragePolicies,
        private CreateCoveragePolicyHandler $createCoveragePolicy,
        private GetCoveragePolicyHandler $getCoveragePolicy,
        private ListCoverageVersionsHandler $listCoverageVersions,
        private CreateCoverageVersionHandler $createCoverageVersion,
        private GetCoverageVersionHandler $getCoverageVersion,
        private UpdateCoverageVersionHandler $updateCoverageVersion,
        private TransitionCoverageVersionHandler $transitionCoverageVersion,
        private ResolveCoveragePolicyHandler $resolveCoveragePolicy,
    ) {}

    public function list(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listCoveragePolicies->handle(new ListCoveragePoliciesCommand($actor, CoveragePolicyFiltersDto::fromValidated($filters)));
    }

    public function create(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new CoveragePolicyResource($this->createCoveragePolicy->handle(new CreateCoveragePolicyCommand($actor, CoveragePolicyDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function policy(AuthenticatedPrincipal $actor, string $id): array
    {
        return (new CoveragePolicyResource($this->getCoveragePolicy->handle(new GetCoveragePolicyCommand($actor, $id))))->resolve();
    }

    public function history(
        AuthenticatedPrincipal $actor,
        string $policyId,
        int $page = 1,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return $this->listCoverageVersions->handle(new ListCoverageVersionsCommand($actor, $policyId, $page, $perPage));
    }

    public function presentVersion(CoveragePolicyVersionRecord $row): array
    {
        return (new CoverageVersionResource($row))->resolve();
    }

    public function createVersion(
        AuthenticatedPrincipal $actor,
        string $policyId,
        array $input,
        string $correlationId,
    ): array {
        return (new CoverageVersionResource($this->createCoverageVersion->handle(new CreateCoverageVersionCommand($actor, $policyId, CoverageVersionDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function version(
        AuthenticatedPrincipal $actor,
        string $policyId,
        string $versionId,
    ): array {
        return (new CoverageVersionResource($this->getCoverageVersion->handle(new GetCoverageVersionCommand($actor, $policyId, $versionId))))->resolve();
    }

    public function update(
        AuthenticatedPrincipal $actor,
        string $policyId,
        string $versionId,
        array $input,
        string $correlationId,
    ): array {
        return (new CoverageVersionResource($this->updateCoverageVersion->handle(new UpdateCoverageVersionCommand($actor, $policyId, $versionId, CoverageVersionChangesDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $policyId,
        string $versionId,
        string $action,
        int $expected,
        ?string $note,
        string $correlationId,
    ): array {
        return (new CoverageVersionResource($this->transitionCoverageVersion->handle(new TransitionCoverageVersionCommand($actor, $policyId, $versionId, $action, $expected, $note, $correlationId))))->resolve();
    }

    public function resolve(
        string $hqId,
        CoverageTarget $target,
        array $input,
        ?string $offeringVersionId = null,
        ?DateTimeInterface $at = null,
    ): ResolveCoveragePolicyResult {
        return $this->resolveCoveragePolicy->handle(new ResolveCoveragePolicyCommand($hqId, $target, CoverageInput::location($input), $offeringVersionId, $at));
    }
}
