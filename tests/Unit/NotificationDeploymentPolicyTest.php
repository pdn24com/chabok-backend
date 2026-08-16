<?php

declare(strict_types=1);

namespace Tests\Unit;

use LogicException;
use Modules\Notification\Infrastructure\Providers\NotificationServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NotificationDeploymentPolicyTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function allowedDeployments(): iterable
    {
        yield 'local deterministic adapter' => ['local', false];
        yield 'test deterministic adapter' => ['testing', false];
        yield 'explicitly approved staging adapter' => ['staging', true];
    }

    #[DataProvider('allowedDeployments')]
    public function test_allowed_deployments_boot(string $environment, bool $allowInStaging): void
    {
        NotificationServiceProvider::assertDeploymentAllowed($environment, $allowInStaging);

        $this->addToAssertionCount(1);
    }

    public function test_staging_fails_closed_without_explicit_approval(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Staging deterministic notifications require explicit approval.');

        NotificationServiceProvider::assertDeploymentAllowed('staging', false);
    }

    #[DataProvider('booleanValues')]
    public function test_production_always_fails_closed(bool $allowInStaging): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Production notification provider credentials and contracts are not approved.');

        NotificationServiceProvider::assertDeploymentAllowed('production', $allowInStaging);
    }

    /** @return iterable<string, array{bool}> */
    public static function booleanValues(): iterable
    {
        yield 'flag disabled' => [false];
        yield 'flag enabled' => [true];
    }
}
