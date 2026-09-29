<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\CurrentCatalogInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;
use Modules\ServiceCatalog\Application\Dto\CatalogRecordDetailDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;
use Modules\ServiceCatalog\Application\Serialization\CatalogValidationDocument;
use Modules\ServiceCatalog\Application\Serialization\ScheduleDocument;
use Modules\ServiceCatalog\Application\Services\CurrentCatalog;
use Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityCommand;
use Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityHandler;
use Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;
use Modules\ServiceCatalog\Domain\Enums\CommitmentScopeType;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final readonly class SaveCatalogRecordHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private ConnectionInterface $connection,
        private CurrentCatalogInterface $currentCatalog,
        private CatalogRecordReaderInterface $catalogRecordReader,
        private ClockInterface $clock,
        private CreateCommitmentScheduleHandler $createCommitmentScheduleHandler,
        private UpdateCommitmentScheduleHandler $updateCommitmentScheduleHandler,
        private ValidateCommitmentScheduleHandler $validateCommitmentScheduleHandler,
        private CreateCatalogIdentityHandler $createCatalogIdentityHandler,
        private UpdateCatalogDraftHandler $updateCatalogDraftHandler,
        private ValidateCatalogDraftHandler $validateCatalogDraftHandler,
        private CatalogRecordChangeRecorderInterface $catalogRecordChangeRecorder,
        private NodeRepositoryInterface $nodeRepository,
        private CatalogRepositoryInterface $catalogRepository,
        private CatalogResourceDefinitionInterface $catalogResourceDefinition,
    ) {}

    public function handle(SaveCatalogRecordCommand $command): CatalogRecordDetailDto
    {
        $actor = $command->actor;
        $resource = $command->resource;
        $id = $command->id;
        $input = clone $command->input;
        $correlation = $command->correlation;
        $this->catalogAccessGuard->authorizeRecord($actor, true);
        $input->validFrom = null;
        $input->validTo = null;
        $input->presentFields = array_values(array_unique([...$input->presentFields, 'valid_from', 'valid_to']));

        return $this->connection->transaction(function () use ($actor, $resource, $id, $input, $correlation): CatalogRecordDetailDto {
            $kind = $this->catalogResourceDefinition->resource($resource);
            $identityId = $kind->identityKey();
            $versionId = $kind->versionKey();
            $fingerprint = CurrentCatalog::fingerprint($input);
            $before = null;
            if ($resource === 'commitment-schedules') {
                $this->assertScheduleScope($actor, $input);
            }
            if ($resource === 'offerings') {
                $this->resolveOfferingDependencies($actor, $input);
            }
            if ($id !== null) {
                $identity = $this->catalogRepository->lockTenantIdentity($kind, $id, $actor->hqId);
                if ($identity === null) {
                    throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
                }
                if ($identity->saved_input_fingerprint === $fingerprint) {
                    return $this->catalogRecordReader->detail($actor, $resource, $id);
                }
                if ((int) $identity->edit_lock !== (int) ($input->expectedVersion ?? 0)) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'servicecatalog.record_changed_since_loaded');
                }
                $before = $this->catalogRecordReader->detail($actor, $resource, $id);
                $previous = $this->catalogRepository->latestVersionOf($kind, $id);
                if ($previous === null) {
                    throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
                }
                $newId = $this->catalogRepository->replicateAsDraft($previous, [

                    'previous_version_id' => $previous->getAttribute($versionId),
                    'version_number' => (int) $previous->version_number + 1,
                    'status' => VersionLifecycleStatus::Draft->value,
                    'lock_version' => 1,
                    'created_by' => $actor->userId,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
                if ($resource === 'commitment-schedules') {
                    $scheduleInput = clone $input;
                    $scheduleInput->expectedVersion = 1;
                    $detail = $this->updateCommitmentScheduleHandler->handle(new UpdateCommitmentScheduleCommand($actor, $newId, $scheduleInput, $correlation));
                } else {
                    $detail = $this->updateCatalogDraftHandler->handle(new UpdateCatalogDraftCommand($actor, $resource, $newId, 1, $input, $correlation));
                }
            } else {
                $detail = $resource === 'commitment-schedules' ? $this->createCommitmentScheduleHandler->handle(new CreateCommitmentScheduleCommand($actor, $input, $correlation)) : $this->createCatalogIdentityHandler->handle(new CreateCatalogIdentityCommand($actor, $resource, $input, $correlation));
                $id = (string) $detail->getAttribute($identityId);
                $newId = (string) $detail->getAttribute($versionId);
            }
            $validation = $resource === 'commitment-schedules' ? $this->validateCommitmentScheduleHandler->handle(new ValidateCommitmentScheduleCommand($actor, $newId, true)) : $this->validateCatalogDraftHandler->handle(new ValidateCatalogDraftCommand($actor, $resource, $newId, true));
            if (! $validation->valid()) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.catalog_validation_failed', details: CatalogValidationDocument::make($validation));
            }
            $this->catalogRepository->supersedePublishedVersions($kind, $id, ['status' => VersionLifecycleStatus::Superseded->value, 'updated_at' => $this->clock->now()]);
            $this->catalogRepository->updateVersion($kind, $newId, [
                'status' => VersionLifecycleStatus::Published->value,
                'published_by' => $actor->userId,
                'published_at' => $this->clock->now(),
                'content_digest' => hash('sha256', json_encode($detail instanceof CommitmentScheduleVersionRecord ? ScheduleDocument::version($detail) : CatalogDocument::version($detail), JSON_THROW_ON_ERROR)),
            ]);
            $this->catalogRepository->bumpIdentityEditLock($kind, $id, ['saved_input_fingerprint' => $fingerprint, 'updated_at' => $this->clock->now()]);
            $after = $this->catalogRecordReader->detail($actor, $resource, $id);
            $this->catalogRecordChangeRecorder->record($actor, $id, $correlation, 'SERVICE_CATALOG_RECORD_SAVED', $before, $after);

            return $after;
        }, attempts: 1);
    }

    private function assertScheduleScope(AuthenticatedPrincipal $actor, CommitmentScheduleDto $input): void
    {
        $nodeIds = [];
        foreach ($input->scopes ?? [] as $scope) {
            if ($scope->type === CommitmentScopeType::Node) {
                $nodeIds[] = $scope->nodeId;
            }
        }
        $nodeIds = array_values(array_unique($nodeIds));
        $availableNodes = count($this->nodeRepository->activeIdsAmong((string) $actor->hqId, $nodeIds));
        if ($availableNodes !== count($nodeIds)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.schedule_scope_is_outside_current_tenant');
        }
    }

    private function resolveOfferingDependencies(AuthenticatedPrincipal $actor, CatalogDraftDto $input): void
    {
        $input->serviceTypeVersionId = $this->currentCatalog->currentVersion(CatalogResource::ServiceType,
            $input->serviceTypeVersionId, $actor->hqId);
        $input->shippingMethodVersionId = $this->currentCatalog->currentVersion(CatalogResource::ShippingMethod,
            $input->shippingMethodVersionId, $actor->hqId);
        $options = $input->optionRules ?? [];
        $revisions = $this->currentCatalog->optionRevisions(array_map(static fn ($option): string => $option->serviceOptionVersionId, $options), $actor->hqId);
        foreach (array_keys($options) as $position => $key) {
            $current = $revisions[$position]->currentVersionId;
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.catalog_dependency_is_inactive_unavailable',
                    details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => 'options']);
            }
            $input->optionRules[$key]->serviceOptionVersionId = $current;
        }
        if (! empty($input->commitmentBinding)) {
            $input->commitmentBinding->commitmentScheduleVersionId = $this->currentCatalog->currentVersion(CatalogResource::CommitmentSchedule, $input->commitmentBinding->commitmentScheduleVersionId, $actor->hqId);
        }
    }
}
