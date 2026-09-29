<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait InstallsNumericSchema
{
    protected function installNumericSchema(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => false]);
        DB::purge('sqlite');
        DB::connection()->getPdo()->sqliteCreateCollation('utf8mb4_bin', 'strcmp');
        $files = glob(base_path('Modules/*/database/migrations/2026_01_01_*.php'));
        usort($files, fn ($left, $right) => basename($left) <=> basename($right));
        foreach ($files as $file) {
            (require $file)->up();
        }
    }
}
