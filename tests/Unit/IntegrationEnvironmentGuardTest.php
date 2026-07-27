<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\IntegrationEnvironmentGuard;

final class IntegrationEnvironmentGuardTest extends TestCase
{
    /** @return array<string, scalar|null> */
    private static function safeEnvironment(): array
    {
        return [
            'app_environment' => 'testing',
            'database_connection' => 'mysql',
            'database_host' => 'mysql-test',
            'database_name' => 'chabok_testing',
            'redis_host' => 'redis-test',
            'redis_database' => '0',
            'redis_cache_host' => 'redis-test',
            'redis_cache_database' => '1',
        ];
    }

    public function test_accepts_the_dedicated_mysql_and_redis_test_services(): void
    {
        IntegrationEnvironmentGuard::assertSafe(self::safeEnvironment());
        $this->addToAssertionCount(1);
    }

    /** @return iterable<string, array{string, scalar|null}> */
    public static function unsafeEnvironmentProvider(): iterable
    {
        yield 'development database' => ['database_name', 'chabok'];
        yield 'empty database' => ['database_name', ''];
        yield 'production-like database' => ['database_name', 'chabok_production'];
        yield 'development mysql host' => ['database_host', 'mysql'];
        yield 'development redis host' => ['redis_host', 'redis'];
        yield 'development cache redis host' => ['redis_cache_host', 'redis'];
        yield 'wrong app environment' => ['app_environment', 'local'];
        yield 'wrong redis database' => ['redis_database', '15'];
        yield 'shared redis databases' => ['redis_cache_database', '0'];
    }

    #[DataProvider('unsafeEnvironmentProvider')]
    public function test_rejects_every_non_dedicated_environment(string $key, mixed $value): void
    {
        $environment = self::safeEnvironment();
        $environment[$key] = $value;

        $this->expectException(RuntimeException::class);
        IntegrationEnvironmentGuard::assertSafe($environment);
    }
}
