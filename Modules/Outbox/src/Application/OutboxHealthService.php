<?php

declare(strict_types=1);

namespace Modules\Outbox\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Modules\Foundation\Application\SensitiveDataRedactor;

final class OutboxHealthService
{
    /** @return array{ready: bool, components: array<string, string>} */
    public function readiness(): array
    {
        $components = ['mysql' => 'DOWN', 'redis' => 'DOWN', 'outbox_worker' => 'STALE'];
        try {
            DB::connection('mysql-health')->selectOne('SELECT 1 AS healthy');
            $components['mysql'] = 'UP';
        } catch (\Throwable $exception) {
            Log::warning('readiness_component_down', [
                'component' => 'mysql',
                'exception_class' => $exception::class,
                'reason' => SensitiveDataRedactor::message($exception->getMessage()),
            ]);
        }
        try {
            if (Redis::connection('health')->ping()) {
                $components['redis'] = 'UP';
            }
            if (Redis::connection('health')->get('chabok:outbox:heartbeat') !== null) {
                $components['outbox_worker'] = 'UP';
            }
        } catch (\Throwable $exception) {
            Log::warning('readiness_component_down', [
                'component' => 'redis',
                'exception_class' => $exception::class,
                'reason' => SensitiveDataRedactor::message($exception->getMessage()),
            ]);
        }

        return [
            'ready' => ! in_array('DOWN', $components, true)
                && $components['outbox_worker'] === 'UP',
            'components' => $components,
        ];
    }
}
