<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\SaveOperationalStatus;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Contracts\OperationalStatusAccessInterface;
use Modules\Consignment\Application\Dto\OperationalStatusViewDto;
use Modules\Consignment\Application\Repositories\OperationalStatusRepositoryInterface;
use Modules\Consignment\Domain\Enums\OperationalStatusScope;
use Modules\Consignment\Infrastructure\Persistence\Models\OperationalStatusRevisionRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\SourceClient;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class SaveOperationalStatusHandler
{
    public function __construct(
        private OperationalStatusAccessInterface $operationalStatusAccess,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private OperationalStatusRepositoryInterface $operationalStatusRepository,
    ) {}

    public function handle(SaveOperationalStatusCommand $command): OperationalStatusViewDto
    {
        $actor = $command->actor;
        $id = $command->id;
        $input = $command->input;
        $correlation = $command->correlation;
        $context = $this->operationalStatusAccess->access($actor, true);

        return $this->connection->transaction(function () use ($actor, $id, $input, $correlation, $context): OperationalStatusViewDto {
            // Serialize namespace reservations, including collisions between global and tenant codes.
            $this->operationalStatusRepository->lockCatalogue();
            $before = $id ? $this->operationalStatusRepository->lockById($id) : null;
            if ($id && ! $before) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $global = $before ? $before->hq_id === null : $input->scope === OperationalStatusScope::Global;
            if ($global && ! $context->isPlatformAdmin || ! $global && ($actor->hqId === null || $before && $before->hq_id !== $actor->hqId)) {
                throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
            }
            if ($before && $before->version !== $input->expectedVersion) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'consignment.status_changed_reload_retry');
            }
            $code = $before?->code ?? $input->code;
            if (! $before && $this->operationalStatusRepository->codeTaken($code, $actor->hqId, $global)) {
                throw new ApiException(ApiErrorCode::ValidationError, 409, 'consignment.status_code_already_exists');
            }
            if ($code === 'D01') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'consignment.d01_is_reserved_legacy_adapter');
            }
            if ($before?->is_system && (! $input->isActive || $input->isTerminal !== $before->is_terminal || $input->statusGroup !== $before->status_group)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'consignment.built_workflow_semantics_cannot_be_changed_through');
            }
            $beforeSnapshot = $before?->attributesToArray();
            $status = $before ?? new StatusRecord;
            $canManage = $global ? $context->isPlatformAdmin : $this->operationalStatusAccess->tenantManager($context);
            $status->forceFill($input->attributes());
            if ($before && ! $status->isDirty()) {
                return new OperationalStatusViewDto($status, $canManage);
            }
            $at = $this->clock->now()->format('Y-m-d H:i:s.u');
            if (! $before) {
                $status->forceFill([
                    'hq_id' => $global ? null : $actor->hqId,
                    'owner_key' => $global ? 'GLOBAL' : $actor->hqId, 'code' => $code,
                    'is_system' => false, 'manifest_enabled' => false, 'created_at' => $at,
                ]);
            }
            $status->forceFill(['version' => $before ? $before->version + 1 : 1, 'updated_at' => $at])->save();
            $afterSnapshot = $status->attributesToArray();
            (new OperationalStatusRevisionRecord)->forceFill([
                'status_id' => $status->getKey(), 'version' => $status->version,
                'actor_id' => $actor->userId, 'snapshot' => $afterSnapshot, 'created_at' => $at,
            ])->save();
            $this->auditWriter->write($actor->hqId, $actor->userId, 'OPERATIONAL_STATUS_SAVED', 'OPERATIONAL_STATUS', $status->status_id, $correlation,
                before: $beforeSnapshot, after: $afterSnapshot, sourceClient: SourceClient::BranchPanel->value);

            return new OperationalStatusViewDto($status, $canManage);
        }, attempts: 1);
    }
}
