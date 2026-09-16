<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Application\AuthorizationService;
use Modules\Foundation\Application\ScopedAccess;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\User\Application\Contracts\UserScopeAuthorizer;

final readonly class AuthorizationUserScopeAuthorizer implements UserScopeAuthorizer
{
    public function __construct(
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private AuthorizationService $authorization,
    )
    {
    }

    public function visibleUserIds(AuthenticatedPrincipal $actor, ?string $nodeId = null): ?array
    {
        $this->authorization->assertPermission($actor, 'iam.users.view', $actor->hqId);
        $context = $this->authorization->resolve($actor);
        $scopes = ScopedAccess::scopes($context, 'iam.users.view');
        if ($nodeId !== null && !$this->scopedAccess->covers($scopes, $actor->hqId, 'NODE', $nodeId)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }
        if ($nodeId === null && collect($scopes)->contains(fn($s) => $s['scope_type'] === 'TENANT')) {
            return null;
        }
        $nodeIds = $nodeId === null ? $this->scopedAccess->nodes($context, 'iam.users.view') : [$nodeId];
        return $this->assignments($actor->hqId)->get()->filter(function ($a) use ($actor, $nodeIds, $scopes, $nodeId) {
            $scope = [
                'scope_type' => $a->scope_type,
                'scope_id' => $a->scope_id,
                'includes_descendants' => (bool) $a->includes_descendants,
            ];
            if ($nodeId === null && $this->scopedAccess->covers($scopes, $actor->hqId, $a->scope_type, $a->scope_id, (bool) $a->includes_descendants)) {
                return true;
            }
            foreach ($nodeIds as $node) {
                if ($this->scopedAccess->covers([$scope], $actor->hqId, 'NODE', $node)) {
                    return true;
                }
            }
            return false;
        })->pluck('user_id')->unique()->values()->all();
    }

    public function assertTarget(AuthenticatedPrincipal $actor, string $userId, string $permission): void
    {
        $this->authorization->assertPermission($actor, $permission, $actor->hqId);
        if (!DB::table('users')->where(['hq_id' => $actor->hqId, 'user_id' => $userId])->exists()) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        if ($permission === 'iam.users.view') {
            $ids = $this->visibleUserIds($actor);
            if ($ids === null || in_array($userId, $ids, true)) {
                return;
            }
        } else {
            $context = $this->authorization->resolve($actor);
            $scopes = ScopedAccess::scopes($context, $permission);
            $assignments = $this->assignments($actor->hqId)->where('a.user_id', $userId)->get();
            if ($assignments->isEmpty() && collect($scopes)->contains(fn($s) => $s['scope_type'] === 'TENANT')) {
                return;
            }
            $allowed = $assignments->isNotEmpty();
            foreach ($assignments as $a) {
                $allowed = $allowed && $this->scopedAccess->covers($scopes, $actor->hqId, $a->scope_type, $a->scope_id, (bool) $a->includes_descendants);
                $codes = DB::table('role_permissions as rp')->join('permissions as p', 'p.permission_id', '=', 'rp.permission_id')->where('rp.role_id', $a->role_id)->where('p.status', 'ACTIVE')->pluck('p.permission_code');
                foreach ($codes as $code) {
                    $allowed = $allowed && $this->scopedAccess->covers(ScopedAccess::scopes($context, $code), $actor->hqId, $a->scope_type, $a->scope_id, (bool) $a->includes_descendants);
                }
            }
            if ($allowed) {
                return;
            }
        }
        throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
    }

    private function assignments(string $hqId): \Illuminate\Database\Query\Builder
    {
        return DB::table('user_role_assignments as a')->join('roles as r', 'r.role_id', '=', 'a.role_id')->where('a.hq_id', $hqId)->where('a.status', 'ACTIVE')->where('r.status', 'ACTIVE')->select('a.*');
    }
}
