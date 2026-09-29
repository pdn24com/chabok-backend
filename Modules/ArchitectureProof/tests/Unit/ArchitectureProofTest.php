<?php

declare(strict_types=1);

namespace Modules\ArchitectureProof\Tests\Unit;

use Modules\ArchitectureProof\Application\Services\ResolveArchitectureProof;
use Modules\ArchitectureProof\Infrastructure\Providers\ArchitectureProofServiceProvider;
use Tests\TestCase;

final class ArchitectureProofTest extends TestCase
{
    public function test_module_provider_and_module_local_autoload_are_active(): void
    {
        self::assertTrue(
            $this->app->providerIsLoaded(ArchitectureProofServiceProvider::class),
        );

        self::assertSame(
            'architecture-proof',
            $this->app->make(ResolveArchitectureProof::class)->moduleId(),
        );
    }
}
