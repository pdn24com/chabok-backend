<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetAssignmentOptions;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Application\Dto\AssignmentAreaOptionDto;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\ScopeTopologyInterface;
use Modules\Foundation\Application\Services\ScopedAccess;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;

final readonly class GetAssignmentOptionsHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ResolveContextHandler $resolveContextHandler,
        private ScopeTopologyInterface $scopeTopology,
        private RoleReaderInterface $roleReader,
        private ScopedAccessInterface $scopedAccess,
        private NodeRepositoryInterface $nodeRepository,
        private RoleRepositoryInterface $roleRepository,
        private AreaRepositoryInterface $areaRepository,
    ) {}

    public function handle(GetAssignmentOptionsCommand $command): GetAssignmentOptionsResult
    {
        $actor = $command->actor;
        $roleId = $command->roleId;
        $this->authorizationGuard->assertPermission($actor, 'iam.roles.assign', $this->authorizationGuard->tenantId($actor));
        $context = $this->resolveContextHandler->handle(new ResolveContextCommand($actor));
        $coverage = $this->scopedAccess->coverage($actor->hqId);
        $codes = ['iam.roles.assign'];
        if ($roleId !== null) {
            $this->authorizationGuard->assertVisibleRole($roleId, $actor->hqId);
            if (! $this->roleRepository->isAssignable($roleId)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.role_is_invalid_tenant_assignments');
            }
            $codes = [...$codes, ...$this->roleReader->permissionCodesForRole($roleId)];
        }
        $covers = function (
            ScopeType $type,
            ?string $id,
            bool $descendants = false,
        ) use ($context, $coverage, $codes): bool {
            foreach ($codes as $code) {
                if (! $coverage->covers(ScopedAccess::scopes($context, $code), $type, $id, $descendants)) {
                    return false;
                }
            }

            return true;
        };
        $areas = $this->areaRepository->activeByTitleKeyedById((string) $actor->hqId);
        $parents = [];
        if ($actor->hqId !== null) {
            foreach ($this->scopeTopology->areaEdges($actor->hqId) as $edge) {
                $parents[$edge->childAreaId] = $edge->parentAreaId;
            }
        }
        $options = [];
        foreach ($areas as $area) {
            if (! $covers(ScopeType::AREA, $area->area_id)) {
                continue;
            }
            $path = [$area->area_title];
            $cursor = $area->area_id;
            $seen = [$cursor => true];
            while (isset($parents[$cursor], $areas[$parents[$cursor]]) && ! isset($seen[$parents[$cursor]])) {
                $cursor = $parents[$cursor];
                $seen[$cursor] = true;
                array_unshift($path, $areas[$cursor]->area_title);
            }
            $options[] = new AssignmentAreaOptionDto($area, implode(' / ', $path), $covers(ScopeType::AREA, $area->area_id, true));
        }
        $nodeIds = $this->scopedAccess->nodes($context, 'iam.roles.assign');
        $nodes = [];
        foreach ($this->nodeRepository->directoryEntries((string) $actor->hqId, $nodeIds, false) as $node) {
            if ($covers(ScopeType::NODE, $node->node_id)) {
                $nodes[] = $node;
            }
        }

        return new GetAssignmentOptionsResult($covers(ScopeType::TENANT, null), $options, $nodes);
    }
}
