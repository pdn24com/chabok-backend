<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Illuminate\Database\QueryException;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\TenantAssignmentWriterInterface;
use Modules\Authorization\Application\Repositories\AssignmentRepositoryInterface;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class TenantAssignmentWriter implements TenantAssignmentWriterInterface
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ClockInterface $clock,
        private NodeRepositoryInterface $nodeRepository,
        private RoleRepositoryInterface $roleRepository,
        private AreaRepositoryInterface $areaRepository,
        private AssignmentRepositoryInterface $assignmentRepository,
    ) {}

    /** @param list<RoleAssignment> $inputs @return list<AssignmentRecord> */
    public function createTenantAssignments(AuthenticatedPrincipal $actor, string $userId, string $hqId, array $inputs): array
    {
        if ($inputs === []) {
            return [];
        }
        $roleIds = [];
        $areaIds = [];
        $nodeIds = [];
        $slots = [];
        foreach ($inputs as $input) {
            $roleIds[] = $input->roleId;
            if ($input->scope->type === ScopeType::AREA) {
                $areaIds[] = $input->scope->id;
            } elseif ($input->scope->type === ScopeType::NODE) {
                $nodeIds[] = $input->scope->id;
            }
            // The existing schema's active_slot unique constraint uses this canonical legacy key.
            $slots[] = hash('sha256', implode('|', [$userId, $input->roleId, $input->scope->type->value, $input->scope->id ?? '-']));
        }
        $roles = $this->roleRepository->activeWithPermissionsKeyedById($roleIds);
        $areas = collect($this->areaRepository->activeIdsAmong($hqId, $areaIds))->flip();
        $nodes = collect($this->nodeRepository->idsAmong($hqId, $nodeIds))->flip();
        $occupied = collect($this->assignmentRepository->occupiedSlots($slots))->flip();
        $delegation = $this->authorizationGuard->delegationSnapshot($actor);
        $rows = [];
        foreach ($inputs as $index => $input) {
            $role = $roles->get($input->roleId);
            if ($role === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.role_is_invalid');
            }
            if (($role->hq_id !== null && $role->hq_id !== $hqId) || $role->role_code === 'platform_super_admin' || $input->scope->type === ScopeType::PLATFORM) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
            }
            $permissionCodes = $role->permissions->pluck('permission_code')->all();
            $this->authorizationGuard->assertPermissionCodesDelegable($permissionCodes, $delegation->permissionCodes);
            $targetExists = match ($input->scope->type) {
                ScopeType::AREA => $areas->has($input->scope->id),
                ScopeType::NODE => $nodes->has($input->scope->id),
                default => true,
            };
            $this->authorizationGuard->assertScopeTarget($userId, $input->scope, $targetExists);
            $this->authorizationGuard->assertSnapshotScope($delegation, $input->scope, ['iam.roles.assign', ...$permissionCodes]);
            $slot = $slots[$index];
            if ($occupied->has($slot)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'authorization.assignment_already_exists');
            }
            $occupied->put($slot, true);
            $at = $this->clock->now();
            $rows[] = [
                'hq_id' => $hqId, 'user_id' => $userId,
                'role_id' => $role->role_id, 'scope_type' => $input->scope->type->value, 'scope_id' => $input->scope->id,
                'includes_descendants' => $input->scope->includesDescendants, 'status' => 'ACTIVE', 'active_slot' => $slot,
                'assigned_by' => $actor->userId, 'created_at' => $at, 'updated_at' => $at,
            ];
        }
        try {
            foreach (array_chunk($rows, 100) as $batch) {
                $this->assignmentRepository->insert($batch);
            }
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            throw new ApiException(ApiErrorCode::Conflict, 409, 'authorization.assignment_already_exists');
        }
        $slots = array_column($rows, 'active_slot');
        $created = $this->assignmentRepository->byActiveSlots($hqId, $slots);

        return array_map(fn (string $slot): AssignmentRecord => $created->get($slot), $slots);
    }
}
