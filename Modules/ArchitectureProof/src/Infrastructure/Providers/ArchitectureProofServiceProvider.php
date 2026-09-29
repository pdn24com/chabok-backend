<?php

declare(strict_types=1);

namespace Modules\ArchitectureProof\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ArchitectureProof\Application\Contracts\ResolveArchitectureProofInterface;
use Modules\ArchitectureProof\Application\Services\ResolveArchitectureProof;

final class ArchitectureProofServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ResolveArchitectureProofInterface::class, ResolveArchitectureProof::class);
        $this->app->singleton(ResolveArchitectureProof::class);
    }
}
