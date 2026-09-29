<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Illuminate\Database\Eloquent\Builder;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Services\ScopedAccess;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class AuthorizationUserScopeAuthorizer implements UserScopeAuthorizerInterface
{
    public function __construct(
        private ScopedAccessInterface $scopedAccess,
        private AuthorizationGuardInterface $authorizationGuard,
        private AccessContextResolverInterface $accessContextResolver,
    ) {}

    public function visibleUserIds(AuthenticatedPrincipal $actor, ?string $nodeId = null): ?array
    {
        $this->authorizationGuard->assertPermission($actor, 'iam.users.view', $actor->hqId);
        $context = $this->accessContextResolver->resolve($actor);
        $scopes = ScopedAccess::scopes($context, 'iam.users.view');
        $coverage = $this->scopedAccess->coverage($actor->hqId);
        if ($nodeId !== null && ! $coverage->covers($scopes, ScopeType::NODE, $nodeId)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
        if ($nodeId === null && collect($scopes)->contains(fn ($s) => $s->type === ScopeType::TENANT)) {
            return null;
        }
        $nodeIds = $nodeId === null ? $this->scopedAccess->nodes($context, 'iam.users.view') : [$nodeId];

        return $this
            ->assignments($actor->hqId)
            ->get()
            ->filter(function ($a) use ($coverage, $nodeIds, $scopes, $nodeId) {
                $scope = new PermissionScope(ScopeType::from($a->scope_type), $a->scope_id, (bool) $a->includes_descendants);
                if ($nodeId === null && $coverage->covers($scopes, ScopeType::from($a->scope_type), $a->scope_id, (bool) $a->includes_descendants)) {
                    return true;
                }
                foreach ($nodeIds as $node) {
                    if ($coverage->covers([$scope], ScopeType::NODE, $node)) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    public function assertTarget(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $permission,
    ): void {
        $this->authorizationGuard->assertPermission($actor, $permission, $actor->hqId);
        if (! UserRecord::query()->where(['hq_id' => $actor->hqId, 'user_id' => $userId])->exists()) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        if ($permission === 'iam.users.view') {
            $ids = $this->visibleUserIds($actor);
            if ($ids === null || in_array($userId, $ids, true)) {
                return;
            }
        } else {
            $context = $this->accessContextResolver->resolve($actor);
            $scopes = ScopedAccess::scopes($context, $permission);
            $coverage = $this->scopedAccess->coverage($actor->hqId);
            $assignments = $this
                ->assignments($actor->hqId)
                ->where('user_id', $userId)
                ->with(['role.permissions' => fn ($permissions) => $permissions->where('permissions.status', 'ACTIVE')])
                ->get();
            if ($assignments->isEmpty() && collect($scopes)->contains(fn ($s) => $s->type === ScopeType::TENANT)) {
                return;
            }
            $allowed = $assignments->isNotEmpty();
            foreach ($assignments as $a) {
                $allowed = $allowed && $coverage->covers($scopes, ScopeType::from($a->scope_type), $a->scope_id, (bool) $a->includes_descendants);
                $codes = $a->role->permissions->pluck('permission_code');
                foreach ($codes as $code) {
                    $allowed = $allowed && $coverage->covers(ScopedAccess::scopes($context, $code), ScopeType::from($a->scope_type), $a->scope_id, (bool) $a->includes_descendants);
                }
            }
            if ($allowed) {
                return;
            }
        }
        throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
    }

    private function assignments(string $hqId): Builder
    {
        return AssignmentRecord::query()->where('hq_id', $hqId)->where('status', 'ACTIVE')->whereHas('role', fn ($role) => $role->where('status', 'ACTIVE'));
    }
}
