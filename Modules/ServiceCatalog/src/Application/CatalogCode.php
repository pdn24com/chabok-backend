<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Illuminate\Support\Facades\DB;

final class CatalogCode
{
    public static function generate(string $table, string $owner): string
    {
        // Callers are in a transaction; serialize automatic allocation per tenant.
        DB::table('hq_tenants')->where('hq_id', $owner)->lockForUpdate()->first();
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $code = (string) random_int(100000, 999999);
            if (!DB::table($table)->where('owner_key', $owner)->where('code', $code)->exists()) {
                return $code;
            }
        }
        throw new \RuntimeException('Unable to allocate catalog code.');
    }
}
