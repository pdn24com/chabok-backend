<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Domain\Policies\EditPricingPolicy;
use PHPUnit\Framework\TestCase;

final class EditPricingImpactTest extends TestCase
{
    public function test_coordinates_require_pricing_unless_geographic_dependencies_allow_them(): void
    {
        $policy = new EditPricingPolicy;
        $before = [
            'sender' => ['city_id' => '18506435', 'latitude' => 35.1],
            'receiver' => [],
            'parcels' => [['weight' => 1, 'content_description' => '213518006']],
        ];
        $after = $before;
        $after['sender']['latitude'] = 35.2;
        $after['sender']['province_id'] = 'derived';
        $after['parcels'][0]['content_description'] = 'new';
        self::assertTrue($policy->changed(ConsignmentInputMapper::draft($before), ConsignmentInputMapper::draft($after), ['contact_name']));
        self::assertFalse($policy->changed(ConsignmentInputMapper::draft($before), ConsignmentInputMapper::draft($after), ['latitude', 'longitude']));
        $after['sender']['city_id'] = 'another-city';
        self::assertTrue($policy->changed(ConsignmentInputMapper::draft($before), ConsignmentInputMapper::draft($after), ['latitude', 'longitude']));
    }
}
