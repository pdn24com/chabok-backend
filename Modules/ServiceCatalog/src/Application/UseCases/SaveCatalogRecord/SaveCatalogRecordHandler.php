<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord;

use Modules\ServiceCatalog\Application\CurrentCatalog;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class SaveCatalogRecordHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordAccessGuard $catalogRecordAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRecordRepository $records,
        private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog,
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordReader $catalogRecordReader,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\CommitmentScheduleService $schedules,
        private \Modules\ServiceCatalog\Application\ServiceCatalogService $catalog,
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordChangeRecorder $catalogRecordChangeRecorder,
    )
    {
    }

    public function handle(SaveCatalogRecordCommand $command): SaveCatalogRecordResult
    {
        return new SaveCatalogRecordResult($this->execute($command->actor, $command->resource, $command->id, $command->input, $command->correlation));
    }

    private function execute(AuthenticatedPrincipal $actor, string $resource, ?string $id, array $input, string $correlation): array
    {
        $this->catalogRecordAccessGuard->authorize($actor, true);
        $input['valid_from'] = null;
        $input['valid_to'] = null;
        return $this->transactions->run(function () use ($actor, $resource, $id, $input, $correlation): array {
            [$identityId, $versionId] = \Modules\ServiceCatalog\Domain\CatalogResource::keys($resource);
            $fingerprint = CurrentCatalog::fingerprint($input);
            $before = null;
            if ($resource === 'commitment-schedules') {
                foreach ($input['scopes'] ?? [] as $scope) {
                    if ($scope['scope_type'] === 'NODE' && !$this->records->activeNode($actor->hqId, $scope['node_id'])) {
                        throw new ApiException(ApiErrorCode::ValidationError, 422, 'The schedule scope is outside the current tenant.');
                    }
                }
            }
            if ($resource === 'offerings') {
                foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $dependency) {
                    $input[$field] = $this->currentCatalog->resolve($dependency, (string) $input[$field], (string) $actor->hqId)[$field];
                }
                foreach ($input['option_rules'] ?? [] as $index => $rule) {
                    $input['option_rules'][$index]['service_option_version_id'] = $this->currentCatalog->resolve('options', (string) $rule['service_option_version_id'], (string) $actor->hqId)['service_option_version_id'];
                }
                if (!empty($input['commitment_binding'])) {
                    $input['commitment_binding']['commitment_schedule_version_id'] = $this->currentCatalog->resolve('commitment-schedules', (string) $input['commitment_binding']['commitment_schedule_version_id'], (string) $actor->hqId)['commitment_schedule_version_id'];
                }
            }
            if ($id !== null) {
                $identity = $this->records->lockIdentity($actor->hqId, $resource, $id);
                if ($identity === null) {
                    throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
                }
                if ($identity->saved_input_fingerprint === $fingerprint) {
                    return $this->catalogRecordReader->detail($actor, $resource, $id);
                }
                if ((int) $identity->edit_lock !== (int) ($input['expected_version'] ?? 0)) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The record changed since it was loaded.');
                }
                $before = $this->catalogRecordReader->detail($actor, $resource, $id);
                $previous = (array) $this->records->latestRevision($resource, $id);
                $newId = $this->identifiers->uuid();
                $copy = $previous;
                $copy[$versionId] = $newId;
                $copy['previous_version_id'] = $previous[$versionId];
                $copy['version_number'] = (int) $previous['version_number'] + 1;
                $copy['status'] = 'DRAFT';
                $copy['lock_version'] = 1;
                foreach (['approved_by', 'approved_at', 'published_by', 'published_at', 'content_digest'] as $field) {
                    $copy[$field] = null;
                }
                $copy['created_by'] = $actor->userId;
                $copy['created_at'] = $this->clock->now();
                $copy['updated_at'] = $this->clock->now();
                $this->records->insertRevision($resource, $copy);
                $detail = $resource === 'commitment-schedules' ? $this->schedules->update($actor, $newId, [...$input, 'expected_version' => 1], $correlation) : $this->catalog->updateDraft($actor, $resource, $newId, 1, $input, $correlation);
            } else {
                $detail = $resource === 'commitment-schedules' ? $this->schedules->create($actor, $input, $correlation) : $this->catalog->createIdentity($actor, $resource, $input, $correlation);
                $id = (string) $detail[$identityId];
                $newId = (string) $detail[$versionId];
            }
            $validation = $resource === 'commitment-schedules' ? $this->schedules->validate($actor, $newId, true) : $this->catalog->validateDraft($actor, $resource, $newId, true);
            if (!$validation['valid']) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Catalog validation failed.', details: $validation);
            }
            $this->records->supersedePublished($resource, $id, $this->clock->now());
            $this->records->updateRevision($resource, $newId, [
                'status' => 'PUBLISHED',
                'published_by' => $actor->userId,
                'published_at' => $this->clock->now(),
                'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR)),
            ]);
            $this->records->updateIdentity($resource, $id, ['saved_input_fingerprint' => $fingerprint, 'updated_at' => $this->clock->now()]);
            $after = $this->catalogRecordReader->detail($actor, $resource, $id);
            $this->catalogRecordChangeRecorder->record($actor, $id, $correlation, 'SERVICE_CATALOG_RECORD_SAVED', $before, $after);
            return $after;
        });
    }
}
