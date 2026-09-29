<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Consignment\Infrastructure\Persistence\Models\OperationalStatusCatalogLockRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;

final class OperationalStatusCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = json_decode(file_get_contents(module_path('Consignment', 'resources/operational-statuses.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($definitions): void {
            OperationalStatusCatalogLockRecord::query()->insertOrIgnore(['id' => 1]);
            OperationalStatusCatalogLockRecord::query()
                ->where('id', 1)
                ->lockForUpdate()
                ->first();
            foreach ($definitions as $position => $definition) {
                if (StatusRecord::query()
                    ->where('owner_key', 'GLOBAL')
                    ->where('code', $definition['code'])
                    ->exists()) {
                    continue;
                }
                (new StatusRecord)->forceFill([
                    ...$definition,

                    'owner_key' => 'GLOBAL',
                    'hq_id' => null,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $position,
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->save();
            }
        });
    }
}
