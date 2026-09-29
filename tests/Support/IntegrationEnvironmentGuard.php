<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class IntegrationEnvironmentGuard
{
    /** @param array<string, scalar|null> $environment */
    public static function assertSafe(array $environment): void
    {
        $required = [
            'app_environment' => 'testing',
            'database_connection' => 'mysql',
            'database_host' => 'mysql-test',
            'database_name' => 'chabok_testing',
            'redis_host' => 'redis-test',
            'redis_cache_host' => 'redis-test',
        ];

        foreach ($required as $key => $expected) {
            $actual = trim((string) ($environment[$key] ?? ''));
            if ($actual !== $expected) {
                throw new RuntimeException(sprintf(
                    'Unsafe integration-test environment: %s must be exactly "%s".',
                    $key,
                    $expected,
                ));
            }
        }

        $redisDatabase = (string) ($environment['redis_database'] ?? '');
        $redisCacheDatabase = (string) ($environment['redis_cache_database'] ?? '');
        if ($redisDatabase !== '0' || $redisCacheDatabase !== '1') {
            throw new RuntimeException(
                'Unsafe integration-test environment: dedicated Redis databases 0 and 1 are required on redis-test.',
            );
        }
    }
}
