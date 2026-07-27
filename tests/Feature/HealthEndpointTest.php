<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    public function test_liveness_endpoints_do_not_query_mysql_or_redis(): void
    {
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });
        Redis::shouldReceive('connection')->never();

        $this->get('/up')->assertOk();
        $this->get('/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'UP']);

        $this->assertSame(0, $queries);
    }
}
