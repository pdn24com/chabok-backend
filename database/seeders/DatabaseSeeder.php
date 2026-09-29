<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Consignment\Infrastructure\Database\Seeders\OperationalStatusCatalogSeeder;
use Modules\Geography\Infrastructure\Database\Seeders\CountrySeeder;
use Modules\Geography\Infrastructure\Database\Seeders\IranGeographySeeder;
use Modules\Pricing\Infrastructure\Database\Seeders\PricingChargeTypeSeeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OperationalStatusCatalogSeeder::class);
        $this->call(AuthorizationCatalogSeeder::class);
        $this->call(AdminUserSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(IranGeographySeeder::class);
        $this->call(PricingChargeTypeSeeder::class);
    }
}
