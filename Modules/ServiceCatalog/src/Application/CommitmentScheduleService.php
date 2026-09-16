<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CommitmentScheduleService
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesHandler $listCommitmentSchedules,
        private \Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesHandler $listPublishedCommitmentSchedules,
        private \Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleHandler $createCommitmentSchedule,
        private \Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleHandler $cloneCommitmentSchedule,
        private \Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleHandler $updateCommitmentSchedule,
        private \Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleHandler $validateCommitmentSchedule,
        private \Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleHandler $transitionCommitmentSchedule,
        private \Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryHandler $getCommitmentScheduleHistory,
        private \Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsHandler $listPickupCommitmentWindows,
        private \Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment\ResolveOfferingCommitmentHandler $resolveOfferingCommitment,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
    )
    {
    }

    public function list(AuthenticatedPrincipal $actor, array $filters): Page
    {
        return $this->listCommitmentSchedules->handle(new \Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesCommand($actor, $filters))->data;
    }

    public function published(AuthenticatedPrincipal $actor, array $includeVersionIds = []): array
    {
        return $this->listPublishedCommitmentSchedules->handle(new \Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesCommand($actor, $includeVersionIds))->data;
    }

    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createCommitmentSchedule->handle(new \Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleCommand($actor, $input, $correlationId))->data;
    }

    public function cloneDraft(AuthenticatedPrincipal $actor, string $identityId, string $correlationId): array
    {
        return $this->cloneCommitmentSchedule->handle(new \Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleCommand($actor, $identityId, $correlationId))->data;
    }

    public function update(AuthenticatedPrincipal $actor, string $versionId, array $input, string $correlationId): array
    {
        return $this->updateCommitmentSchedule->handle(new \Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleCommand($actor, $versionId, $input, $correlationId))->data;
    }

    public function validate(AuthenticatedPrincipal $actor, string $versionId, bool $automatic = false): array
    {
        return $this->validateCommitmentSchedule->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleCommand($actor, $versionId, $automatic))->data;
    }

    public function transition(AuthenticatedPrincipal $actor, string $versionId, string $action, string $correlationId): array
    {
        return $this->transitionCommitmentSchedule->handle(new \Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleCommand($actor, $versionId, $action, $correlationId))->data;
    }

    public function history(AuthenticatedPrincipal $actor, string $identityId): array
    {
        return $this->getCommitmentScheduleHistory->handle(new \Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryCommand($actor, $identityId))->data;
    }

    public function pickupWindows(AuthenticatedPrincipal $actor, string $nodeId, ?string $at = null): array
    {
        return $this->listPickupCommitmentWindows->handle(new \Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsCommand($actor, $nodeId, $at))->data;
    }

    public function resolveForOffering(string $offeringVersionId, array $context, bool $requireSelection = true): ?array
    {
        return $this->resolveOfferingCommitment->handle(new \Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment\ResolveOfferingCommitmentCommand($offeringVersionId, $context, $requireSelection))->data;
    }

    public function versionDetail(AuthenticatedPrincipal $actor, string $versionId): array
    {
        return $this->scheduleReader->versionDetail($actor, $versionId);
    }
}
