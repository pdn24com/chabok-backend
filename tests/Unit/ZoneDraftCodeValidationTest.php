<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\TestCase;

final class ZoneDraftCodeValidationTest extends TestCase
{
    public function test_zone_defaults_and_legacy_codes_are_accepted_but_malformed_codes_are_rejected(): void
    {
        $rules = \Modules\Pricing\Presentation\Http\Requests\PricingRequestRules::zoneSetRules(true);
        $validator = new Factory(new Translator(new ArrayLoader(), 'en'));
        $input = [
            'code' => '123456',
            'title' => 'Zone group',
            'purpose' => 'SALES',
            'zones' => [[
                'code' => 'A',
                'title' => 'First zone',
                'rank' => 1,
                'members' => [['member_type' => 'PROVINCE', 'province_id' => '00000000-0000-4000-8000-000000000001']],
            ]],
        ];
        self::assertTrue($validator->make($input, $rules)->passes());
        $input['code'] = 'ZS_LEGACY';
        $input['zones'][0]['code'] = 'ZONE_OLD';
        self::assertTrue($validator->make($input, $rules)->passes());
        $input['code'] = '12345';
        self::assertTrue($validator->make($input, $rules)->errors()->has('code'));
        $input['code'] = '123456';
        $input['zones'][0]['code'] = '!';
        self::assertTrue($validator->make($input, $rules)->errors()->has('zones.0.code'));
    }
}
