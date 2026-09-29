<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app['events']->listen(\Illuminate\Database\Events\ConnectionEstablished::class, static function ($event): void {
            if ($event->connection->getDriverName() === 'sqlite') {
                $event->connection->getPdo()->sqliteCreateCollation('utf8mb4_bin', 'strcmp');
            }
        });

        return $app;
    }

    protected function assertDatabaseHasPublic($table, array $data = [], $connection = null)
    {
        $database = \Illuminate\Support\Facades\DB::connection($connection);
        $identity = \Modules\Foundation\Infrastructure\Persistence\RecordSchema::IDENTITY_NAMES[$table] ?? null;
        if ($identity !== null && array_key_exists($identity, $data)) {
            $data['id'] = $data[$identity];
            unset($data[$identity]);
        }

        return $this->assertDatabaseHas($table, $data, $connection);
    }

    protected function assertDatabaseMissingPublic($table, array $data = [], $connection = null)
    {
        $database = \Illuminate\Support\Facades\DB::connection($connection);
        $identity = \Modules\Foundation\Infrastructure\Persistence\RecordSchema::IDENTITY_NAMES[$table] ?? null;
        if ($identity !== null && array_key_exists($identity, $data)) {
            $data['id'] = $data[$identity];
            unset($data[$identity]);
        }

        return $this->assertDatabaseMissing($table, $data, $connection);
    }
}
