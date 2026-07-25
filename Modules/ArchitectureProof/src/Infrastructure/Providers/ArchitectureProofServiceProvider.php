<?php

declare(strict_types=1);

namespace Modules\ArchitectureProof\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ArchitectureProof\Application\ResolveArchitectureProof;

final class ArchitectureProofServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ResolveArchitectureProof::class);
    }
}
