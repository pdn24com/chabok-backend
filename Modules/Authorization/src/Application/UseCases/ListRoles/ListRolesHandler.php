<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListRoles;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListRolesHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
    )
    {
    }

    public function handle(ListRolesCommand $command): ListRolesResult
    {
        return new ListRolesResult($this->execute($command->actor));
    }

    private function execute(AuthenticatedPrincipal $actor): array
    {
        $this->authorizationGuard->assertRoleReadAccess($actor);
        return array_map(fn($id): array => $this->roleReader->rolePayload((string) $id), $this->repository->visibleRoleIds($actor->hqId));
    }
}
