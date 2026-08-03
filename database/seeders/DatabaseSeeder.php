<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Pricing\Infrastructure\Database\Seeders\PricingChargeTypeSeeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AuthorizationCatalogSeeder::class);
        $this->call(PricingChargeTypeSeeder::class);
    }
}
