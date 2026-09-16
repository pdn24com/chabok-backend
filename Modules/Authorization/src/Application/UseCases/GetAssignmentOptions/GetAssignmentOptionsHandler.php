<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetAssignmentOptions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetAssignmentOptionsHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler $resolveContext,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
    )
    {
    }

    public function handle(GetAssignmentOptionsCommand $command): GetAssignmentOptionsResult
    {
        return new GetAssignmentOptionsResult($this->execute($command->actor, $command->roleId));
    }

    private function execute(AuthenticatedPrincipal $actor, ?string $roleId = null): array
    {
        $this->authorizationGuard->assertPermission($actor, 'iam.roles.assign', $this->authorizationGuard->tenantId($actor));
        $context = $this->resolveContext->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($actor))->data;
        $scopes = \Modules\Foundation\Application\ScopedAccess::scopes($context, 'iam.roles.assign');
        $codes = ['iam.roles.assign'];
        if ($roleId !== null) {
            $this->authorizationGuard->assertVisibleRole($roleId, $actor->hqId);
            if (!$this->repository->isActiveTenantRole($roleId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The role is invalid for tenant assignments.');
            }
            $codes = [...$codes, ...$this->roleReader->permissionCodesForRole($roleId)];
        }
        $covers = function (string $type, ?string $id, bool $descendants = false) use ($context, $actor, $codes): bool {
            foreach ($codes as $code) {
                if (!$this->scopedAccess->covers(\Modules\Foundation\Application\ScopedAccess::scopes($context, $code), $actor->hqId, $type, $id, $descendants)) {
                    return false;
                }
            }
            return true;
        };
        $areas = $this->repository->activeAreas($actor->hqId);
        $parents = $this->repository->areaParents($actor->hqId);
        $options = [];
        foreach ($areas as $area) {
            if (!$covers('AREA', $area->area_id)) {
                continue;
            }
            $path = [$area->area_title];
            $cursor = $area->area_id;
            $seen = [$cursor => true];
            while (isset($parents[$cursor], $areas[$parents[$cursor]]) && !isset($seen[$parents[$cursor]])) {
                $cursor = $parents[$cursor];
                $seen[$cursor] = true;
                array_unshift($path, $areas[$cursor]->area_title);
            }
            $options[] = [
                'area_id' => $area->area_id,
                'area_title' => $area->area_title,
                'path' => implode(' / ', $path),
                'can_include_descendants' => $covers('AREA', $area->area_id, true),
            ];
        }
        return [
            'tenant_allowed' => $covers('TENANT', null),
            'areas' => $options,
            'nodes' => array_values(array_map(fn($n) => (array) $n, array_filter($this->repository->assignmentNodes($actor->hqId, $this->scopedAccess->nodes($context, 'iam.roles.assign')), fn($n) => $covers('NODE', $n->node_id)))),
        ];
    }
}
