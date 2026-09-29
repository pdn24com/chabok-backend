<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Pricing\Domain\Enums\ZoneMemberType;
use Modules\Pricing\Domain\Validators\ZoneMembershipValidator;
use Modules\Pricing\Domain\ValueObjects\ZoneMembership;
use PHPUnit\Framework\TestCase;

final class ZoneMembershipValidatorTest extends TestCase
{
    public function test_canonical_city_ids_determine_duplicates_across_zones(): void
    {
        $left = new ZoneMembership('212432914', ZoneMemberType::CITY, 'old city name', null, '18506435', '125339763');
        $right = new ZoneMembership('65158786', ZoneMemberType::CITY, 'new city name', null, '18506435', '125339763');
        self::assertTrue((new ZoneMembershipValidator)->ambiguous([$left, $right]));
        self::assertFalse((new ZoneMembershipValidator)->ambiguous([$left, new ZoneMembership('212432914', ZoneMemberType::CITY, '', null, '18506435', '125339763')]));
    }

    public function test_postal_endpoints_are_inclusive_and_legacy_lengths_remain_distinct(): void
    {
        $left = new ZoneMembership('212432914', ZoneMemberType::POSTAL_RANGE, '1000000000', '2000000000', null, null);
        $touching = new ZoneMembership('65158786', ZoneMemberType::POSTAL_RANGE, '2000000000', '3000000000', null, null);
        $separate = new ZoneMembership('65158786', ZoneMemberType::POSTAL_RANGE, '2000000001', '3000000000', null, null);
        $legacy = new ZoneMembership('65158786', ZoneMemberType::POSTAL_RANGE, '100000', '300000', null, null);
        $validator = new ZoneMembershipValidator;
        self::assertTrue($validator->ambiguous([$left, $touching]));
        self::assertFalse($validator->ambiguous([$left, $separate]));
        self::assertFalse($validator->ambiguous([$left, $legacy]));
    }

    public function test_reference_matching_is_case_insensitive_but_preserves_member_type(): void
    {
        $left = new ZoneMembership('212432914', ZoneMemberType::EXPLICIT_OVERRIDE, 'North', null, null, null);
        $right = new ZoneMembership('65158786', ZoneMemberType::EXPLICIT_OVERRIDE, 'NORTH', null, null, null);
        self::assertTrue((new ZoneMembershipValidator)->ambiguous([$left, $right]));
        self::assertFalse((new ZoneMembershipValidator)->ambiguous([$left, new ZoneMembership('65158786', ZoneMemberType::PROVINCE, 'NORTH', null, null, null)]));
    }

    public function test_polygon_members_are_checked_by_geometry_not_reference_identity(): void
    {
        $left = new ZoneMembership('212432914', ZoneMemberType::POLYGON, '', null, null, null);
        $right = new ZoneMembership('65158786', ZoneMemberType::POLYGON, '', null, null, null);
        self::assertFalse((new ZoneMembershipValidator)->ambiguous([$left, $right]));
    }
}
