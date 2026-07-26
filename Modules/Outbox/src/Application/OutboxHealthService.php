<?php

declare(strict_types=1);

namespace Modules\Outbox\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class OutboxHealthService
{
    /** @return array{ready: bool, components: array<string, string>} */
    public function readiness(): array
    {
        $components = ['mysql' => 'DOWN', 'redis' => 'DOWN', 'outbox_worker' => 'STALE'];
        try {
            DB::selectOne('SELECT 1 AS healthy');
            $components['mysql'] = 'UP';
        } catch (\Throwable) {
        }
        try {
            if (Redis::connection()->ping()) {
                $components['redis'] = 'UP';
            }
            if (Redis::connection('cache')->get('chabok:outbox:heartbeat') !== null) {
                $components['outbox_worker'] = 'UP';
            }
        } catch (\Throwable) {
        }

        return [
            'ready' => ! in_array('DOWN', $components, true)
                && $components['outbox_worker'] === 'UP',
            'components' => $components,
        ];
    }
}
