<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListPermissions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPermissionsHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
    )
    {
    }

    public function handle(ListPermissionsCommand $command): ListPermissionsResult
    {
        return new ListPermissionsResult($this->execute($command->actor, $command->moduleCode));
    }

    private function execute(AuthenticatedPrincipal $actor, ?string $moduleCode): array
    {
        $this->authorizationGuard->assertRoleReadAccess($actor);
        $delegable = $this->authorizationGuard->delegableCodes($actor, true);
        $canManage = in_array('iam.roles.manage', $delegable, true);
        return array_map(fn($row): array => [...(array) $row, 'can_grant' => $canManage && in_array($row->permission_code, $delegable, true)], $this->repository->permissions($moduleCode));
    }
}
