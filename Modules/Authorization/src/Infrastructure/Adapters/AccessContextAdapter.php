<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class AccessContextAdapter implements AccessContextResolverInterface
{
    public function __construct(private ResolveContextHandler $resolveContextHandler) {}

    public function resolve(AuthenticatedPrincipal $principal): AccessContextDto
    {
        return $this->resolveContextHandler->handle(new ResolveContextCommand($principal));
    }
}
