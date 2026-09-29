<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListPermissions;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Dto\PermissionViewDto;
use Modules\Authorization\Application\Repositories\PermissionRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\PermissionRecord;

final readonly class ListPermissionsHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private PermissionRepositoryInterface $permissionRepository,
    ) {}

    public function handle(ListPermissionsCommand $command): array
    {
        $actor = $command->actor;
        $moduleCode = $command->moduleCode;
        $this->authorizationGuard->assertRoleReadAccess($actor);
        $delegable = $this->authorizationGuard->delegableCodes($actor, true);
        $canManage = in_array('iam.roles.manage', $delegable, true);

        return array_map(fn (PermissionRecord $row): PermissionViewDto => new PermissionViewDto(
            $row, $canManage && in_array($row->permission_code, $delegable, true),
        ), $this->permissionRepository->catalogue($moduleCode));
    }
}
