<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\SaveOperationalStatus;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class SaveOperationalStatusHandler
{
    public function __construct(
        private \Modules\Consignment\Application\Services\OperationalStatusAccess $operationalStatusAccess,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Consignment\Application\Repositories\OperationalStatusRepository $statuses,
        private \Modules\Consignment\Application\Services\OperationalStatusProjection $operationalStatusProjection,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
    )
    {
    }

    public function handle(SaveOperationalStatusCommand $command): SaveOperationalStatusResult
    {
        return new SaveOperationalStatusResult($this->execute($command->actor, $command->id, $command->input, $command->correlation));
    }

    private function execute(AuthenticatedPrincipal $actor, ?string $id, array $input, string $correlation): array
    {
        $context = $this->operationalStatusAccess->access($actor, true);
        return $this->transactions->run(function () use ($actor, $id, $input, $correlation, $context) {
            $this->statuses->lockCatalog();
            $before = $id ? $this->statuses->lockStatus($id) : null;
            if ($id && !$before) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $global = $before ? $before->hq_id === null : ($input['scope'] ?? 'TENANT') === 'GLOBAL';
            if ($global && empty($context['is_platform_admin']) || !$global && ($actor->hqId === null || $before && $before->hq_id !== $actor->hqId)) {
                throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
            }
            if ($before && (int) $before->version !== (int) $input['expected_version']) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'Status changed; reload and retry.');
            }
            $code = $before?->code ?? $input['code'];
            if (!$before && $this->statuses->codeExists($code, $global, $actor->hqId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 409, 'This status code already exists.');
            }
            if ($code === 'D01') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'D01 is reserved for the legacy adapter.');
            }
            if ($before?->is_system && (!$input['is_active'] || (bool) $input['is_terminal'] !== (bool) $before->is_terminal || ($input['status_group'] ?? null) !== $before->status_group)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Built-in workflow semantics cannot be changed through status presentation settings.');
            }
            $fields = array_intersect_key($input, array_flip([
                'title_fa',
                'title_en',
                'partial_title_fa',
                'partial_title_en',
                'tone',
                'status_group',
                'is_terminal',
                'is_active',
                'sort_order',
            ]));
            if ($before && array_filter($fields, fn($value, $key) => $value != $before->{$key}, ARRAY_FILTER_USE_BOTH) === []) {
                return $this->operationalStatusProjection->present((array) $before, $context, $actor);
            }
            $row = [
                ...$before ? (array) $before : [
                    'status_id' => $this->identifiers->uuid(),
                    'hq_id' => $global ? null : $actor->hqId,
                    'owner_key' => $global ? 'GLOBAL' : $actor->hqId,
                    'code' => $code,
                    'is_system' => false,
                    'manifest_enabled' => false,
                    'created_at' => $this->clock->now(),
                ],
                ...$fields,
                'version' => $before ? $before->version + 1 : 1,
                'updated_at' => $this->clock->now(),
            ];
            if ($before) {
                $this->statuses->update($id, $row);
            } else {
                $this->statuses->insert($row);
            }
            $this->statuses->appendRevision([
                'revision_id' => $this->identifiers->uuid(),
                'status_id' => $row['status_id'],
                'version' => $row['version'],
                'actor_id' => $actor->userId,
                'snapshot' => json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created_at' => $this->clock->now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'OPERATIONAL_STATUS_SAVED', 'OPERATIONAL_STATUS', $row['status_id'], $correlation, before: $before ? (array) $before : null, after: $row, sourceClient: 'BRANCH_PANEL');
            return $this->operationalStatusProjection->present($row, $context, $actor);
        });
    }
}
