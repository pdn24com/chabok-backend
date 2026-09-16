<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetRole;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetRoleHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
    )
    {
    }

    public function handle(GetRoleCommand $command): GetRoleResult
    {
        return new GetRoleResult($this->execute($command->actor, $command->roleId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $roleId): array
    {
        $this->authorizationGuard->assertRoleReadAccess($actor);
        $this->authorizationGuard->assertVisibleRole($roleId, $actor->hqId);
        return $this->roleReader->rolePayload($roleId);
    }
}
