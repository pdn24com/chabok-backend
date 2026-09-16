<?php
declare(strict_types=1);
namespace Modules\User\Application\Contracts;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
interface UserScopeAuthorizer
{
    public function visibleUserIds(AuthenticatedPrincipal $actor, ?string $nodeId = null): ?array;
    public function assertTarget(AuthenticatedPrincipal $actor, string $userId, string $permission): void;
}
