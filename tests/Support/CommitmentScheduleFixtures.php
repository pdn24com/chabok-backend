<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleFiltersDto;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryCommand;
use Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryHandler;
use Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesCommand;
use Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesHandler;
use Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsCommand;
use Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsHandler;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesCommand;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesHandler;
use Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment\ResolveOfferingCommitmentCommand;
use Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment\ResolveOfferingCommitmentHandler;
use Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogValidationResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogVersionResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\OfferingCommitmentResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\PickupWindowOptionResource;

final readonly class CommitmentScheduleFixtures
{
    public function __construct(
        private ListCommitmentSchedulesHandler $listCommitmentSchedules,
        private ListPublishedCommitmentSchedulesHandler $listPublishedCommitmentSchedules,
        private CreateCommitmentScheduleHandler $createCommitmentSchedule,
        private CloneCommitmentScheduleHandler $cloneCommitmentSchedule,
        private UpdateCommitmentScheduleHandler $updateCommitmentSchedule,
        private ValidateCommitmentScheduleHandler $validateCommitmentSchedule,
        private TransitionCommitmentScheduleHandler $transitionCommitmentSchedule,
        private GetCommitmentScheduleHistoryHandler $getCommitmentScheduleHistory,
        private ListPickupCommitmentWindowsHandler $listPickupCommitmentWindows,
        private ResolveOfferingCommitmentHandler $resolveOfferingCommitment,
        private ScheduleReaderInterface $scheduleReader,
    ) {}

    public function list(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        return $this->listCommitmentSchedules->handle(new ListCommitmentSchedulesCommand($actor, CommitmentScheduleFiltersDto::fromValidated($filters)));
    }

    public function published(AuthenticatedPrincipal $actor, array $includeVersionIds = []): array
    {
        return $this->listPublishedCommitmentSchedules->handle(new ListPublishedCommitmentSchedulesCommand($actor, $includeVersionIds));
    }

    public function create(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->createCommitmentSchedule->handle(new CreateCommitmentScheduleCommand($actor, CommitmentScheduleDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function cloneDraft(
        AuthenticatedPrincipal $actor,
        string $identityId,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->cloneCommitmentSchedule->handle(new CloneCommitmentScheduleCommand($actor, $identityId, $correlationId))))->resolve();
    }

    public function update(
        AuthenticatedPrincipal $actor,
        string $versionId,
        array $input,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->updateCommitmentSchedule->handle(new UpdateCommitmentScheduleCommand($actor, $versionId, CommitmentScheduleDto::fromValidated($input), $correlationId))))->resolve();
    }

    public function validate(
        AuthenticatedPrincipal $actor,
        string $versionId,
        bool $automatic = false,
    ): array {
        return (new CatalogValidationResource($this->validateCommitmentSchedule->handle(new ValidateCommitmentScheduleCommand($actor, $versionId, $automatic))))->resolve();
    }

    public function transition(
        AuthenticatedPrincipal $actor,
        string $versionId,
        string $action,
        string $correlationId,
    ): array {
        return (new CatalogVersionResource($this->transitionCommitmentSchedule->handle(new TransitionCommitmentScheduleCommand($actor, $versionId, $action, $correlationId))))->resolve();
    }

    public function history(AuthenticatedPrincipal $actor, string $identityId): array
    {
        return $this->getCommitmentScheduleHistory->handle(new GetCommitmentScheduleHistoryCommand($actor, $identityId));
    }

    public function pickupWindows(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        ?string $at = null,
    ): array {
        return PickupWindowOptionResource::collection($this->listPickupCommitmentWindows->handle(new ListPickupCommitmentWindowsCommand($actor, $nodeId, $at)))->resolve();
    }

    public function resolveForOffering(
        string $offeringVersionId,
        array $context,
        bool $requireSelection = true,
    ): ?array {
        $commitment = $this->resolveOfferingCommitment->handle(new ResolveOfferingCommitmentCommand($offeringVersionId, OfferingSelectionInput::fromArray($context), $requireSelection));

        return $commitment === null ? null : (new OfferingCommitmentResource($commitment))->resolve();
    }

    public function versionDetail(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return (new CatalogVersionResource($this->scheduleReader->versionDetail($actor, $versionId)))->resolve();
    }
}
