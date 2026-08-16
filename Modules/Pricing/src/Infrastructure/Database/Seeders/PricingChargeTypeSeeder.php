<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        foreach ($definitions as $code => [$category, $mapping, $taxable]) {
            DB::table('pricing_charge_types')->updateOrInsert(['code' => $code], [
                'charge_type_id' => DB::table('pricing_charge_types')->where('code', $code)->value('charge_type_id') ?: $this->id($code),
                'category' => $category, 'accounting_mapping_key' => $mapping, 'taxable' => $taxable,
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function id(string $code): string
    {
        $hex = substr(hash('sha256', 'chabok-pricing-charge-'.$code), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-8'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
