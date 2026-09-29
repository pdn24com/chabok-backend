<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeTypeRecord;

final class PricingChargeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'BASE_FREIGHT' => ['BASE', 'FREIGHT_REVENUE', true],
            'PICKUP_FEE' => ['SURCHARGE', 'PICKUP_REVENUE', true],
            'DELIVERY_FEE' => ['SURCHARGE', 'DELIVERY_REVENUE', true],
            'REMOTE_AREA' => ['SURCHARGE', 'REMOTE_AREA_FEE', true],
            'EXTRA_PARCEL' => ['SURCHARGE', 'EXTRA_PARCEL_FEE', true],
            'INSURANCE_FEE' => ['SURCHARGE', 'INSURANCE_SERVICE_FEE', true],
            'INSURANCE' => ['SURCHARGE', 'INSURANCE_PREMIUM', true],
            'COD_FEE' => ['SURCHARGE', 'COD_SERVICE_FEE', true],
            'FUEL_SURCHARGE' => ['SURCHARGE', 'FUEL_SURCHARGE', true],
            'DISCOUNT' => ['DISCOUNT', 'CUSTOMER_DISCOUNT', false],
            'TAX' => ['TAX', 'OUTPUT_TAX', false],
            'COMMISSION' => ['COMMISSION', 'PARTNER_COMMISSION', false],
        ];
        $rows = [];
        foreach ($definitions as $code => [$category, $mapping, $taxable]) {
            $rows[] = [
                'code' => $code,
                'category' => $category,
                'accounting_mapping_key' => $mapping,
                'taxable' => $taxable,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        PricingChargeTypeRecord::query()->upsert($rows, ['code'], ['category', 'accounting_mapping_key', 'taxable', 'active', 'created_at', 'updated_at']);
    }
}
