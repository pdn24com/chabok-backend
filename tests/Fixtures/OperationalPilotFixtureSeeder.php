<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Geography\Infrastructure\Database\Seeders\IranGeographySeeder;
use Modules\Pricing\Infrastructure\Database\Seeders\PricingChargeTypeSeeder;
use RuntimeException;
use Tests\Support\CommitmentScheduleFixtures;
use Tests\Support\GeographyIds;
use Tests\Support\PricingFixtures as PricingService;
use Tests\Support\RecordFixtureQuery;
use Tests\Support\ServiceCatalogFixtures;

/**
 * Explicit isolated-test fixture. Production boot and runtime commands never call it.
 * Passwords are intentionally not embedded. Set CHABOK_PILOT_PASSWORD to make actors login-capable.
 */
final class OperationalPilotFixtureSeeder extends Seeder
{
    public const HQ_ID = '1466898563';

    public const AREA_ID = '1261409478';

    public const TBZ_BRANCH_ID = '1737068845';

    public const TBZ_HUB_ID = '1409735730';

    public const THR_HUB_ID = '138083089';

    public const TAJRISH_BRANCH_ID = '201974169';

    public const ROUTE_ID = '1074495181';

    public const PICKUP_DRIVER_ID = '1312373327';

    public const LINEHAUL_DRIVER_ID = '1613132355';

    public const DELIVERY_DRIVER_ID = '1192688854';

    public const TBZ_HUB_LINEHAUL_DRIVER_ID = '398759802';

    public const THR_HUB_LINEHAUL_DRIVER_ID = '533049431';

    public const VEHICLE_ID = '564600692';

    public const TBZ_HUB_VEHICLE_ID = '1556626579';

    public const THR_HUB_VEHICLE_ID = '1966973905';

    /** @var array<string,array{0:string,1:string,2:string}> */
    public const ACTORS = [
        'pilot.catalog.admin' => ['657444198', 'مدیر', 'تعرفه و کاتالوگ'],
        'pilot.catalog.checker' => ['583606447', 'تأییدکننده', 'کاتالوگ'],
        'pilot.pickup.dispatcher' => ['930184837', 'دیسپچر', 'جمع‌آوری'],
        'pilot.pickup.driver' => ['260916583', 'راننده', 'جمع‌آوری تبریز'],
        'pilot.tbz.branch.operator' => ['753394808', 'اپراتور', 'شعبه تبریز ۴'],
        'pilot.tbz.hub.operator' => ['1897411992', 'اپراتور', 'هاب تبریز'],
        'pilot.linehaul.driver' => ['1036182874', 'راننده', 'خطی تبریز تهران'],
        'pilot.thr.hub.operator' => ['91320209', 'اپراتور', 'هاب تهران'],
        'pilot.tajrish.operator' => ['64888807', 'اپراتور', 'شعبه تجریش'],
        'pilot.delivery.dispatcher' => ['80270614', 'دیسپچر', 'تحویل تجریش'],
        'pilot.delivery.driver' => ['1843307162', 'راننده', 'تحویل تجریش'],
    ];

    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('The operational integration fixture is restricted to isolated tests.');
        }
        app(AuthorizationCatalogSeeder::class)->run();
        app(IranGeographySeeder::class)->run();
        app(PricingChargeTypeSeeder::class)->run();
        $this->seedNetworkAndActors();
        $this->seedCommercialConfiguration();
    }

    private function seedNetworkAndActors(): void
    {
        DB::transaction(function (): void {
            RecordFixtureQuery::table('hq_tenants')->updateOrInsert(['hq_id' => self::HQ_ID], ['hq_code' => 'PILOT-TBZ-THR', 'hq_title' => 'ستاد پایلوت تبریز تهران', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
            RecordFixtureQuery::table('areas')->updateOrInsert(['area_id' => self::AREA_ID], ['hq_id' => self::HQ_ID, 'area_title' => 'شبکه پایلوت تبریز تهران', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
            foreach ([
                self::TBZ_BRANCH_ID => ['TBZ-BR-04', 'شعبه تبریز ۴', 'BRANCH', ['PICKUP', 'LINEHAUL']],
                self::TBZ_HUB_ID => ['TBZ-HUB-MAIN', 'هاب اصلی تبریز', 'HUB', ['CONSOLIDATION', 'LINEHAUL']],
                self::THR_HUB_ID => ['THR-HUB-MAIN', 'هاب اصلی تهران', 'HUB', ['CONSOLIDATION', 'LINEHAUL']],
                self::TAJRISH_BRANCH_ID => ['THR-BR-05-TAJRISH', 'شعبه تهران ۵، تجریش', 'BRANCH', ['LINEHAUL', 'DELIVERY']],
            ] as $id => [$code, $title, $type, $capabilities]) {
                RecordFixtureQuery::table('nodes')->updateOrInsert(['node_id' => $id], ['hq_id' => self::HQ_ID, 'area_id' => self::AREA_ID, 'node_code' => $code, 'node_title' => $title, 'node_type' => $type, 'capabilities' => json_encode($capabilities, JSON_THROW_ON_ERROR), 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach (self::ACTORS as $username => [$id, $first, $last]) {
                RecordFixtureQuery::table('users')->updateOrInsert(['user_id' => $id], ['hq_id' => self::HQ_ID, 'username' => $username, 'normalized_username' => $username, 'mobile' => null, 'normalized_mobile' => null, 'email' => null, 'normalized_email' => null, 'first_name' => $first, 'last_name' => $last, 'display_name' => "{$first} {$last}", 'status' => 'ACTIVE', 'must_change_password' => false, 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $password = (string) env('CHABOK_PILOT_PASSWORD', '');
            if ($password !== '') {
                foreach (self::ACTORS as [$id]) {
                    RecordFixtureQuery::table('authentication_credentials')->updateOrInsert(['user_id' => $id], ['credential_id' => $this->id("credential:{$id}"), 'password_hash' => password_hash($password, PASSWORD_ARGON2ID), 'algorithm' => 'argon2id', 'algorithm_version' => 1, 'password_changed_at' => now(), 'failed_attempt_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            foreach (['Foundation', 'IAM', 'Consignment', 'Parcel', 'Manifest', 'Audit', 'Pickup', 'Driver', 'LiveOperations', 'Exception', 'ServiceCatalog', 'Pricing'] as $module) {
                RecordFixtureQuery::table('tenant_module_entitlements')->updateOrInsert(['hq_id' => self::HQ_ID, 'module_code' => $module], ['entitlement_id' => $this->id("entitlement:{$module}"), 'status' => 'ENABLED', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->assign('pilot.catalog.admin', 'hq_admin', 'TENANT', null);
            $this->assign('pilot.catalog.admin', 'branch_manager', 'TENANT', null);
            $this->assign('pilot.catalog.checker', 'hq_admin', 'TENANT', null);
            $this->assign('pilot.pickup.dispatcher', 'branch_operator', 'NODE', self::TBZ_BRANCH_ID);
            $this->assign('pilot.pickup.dispatcher', 'dispatcher', 'NODE', self::TBZ_BRANCH_ID);
            $this->operator('pilot.tbz.branch.operator', self::TBZ_BRANCH_ID, 'branch_operator');
            $this->operator('pilot.tbz.hub.operator', self::TBZ_HUB_ID, 'branch_operator');
            $this->operator('pilot.thr.hub.operator', self::THR_HUB_ID, 'branch_operator');
            $this->operator('pilot.tajrish.operator', self::TAJRISH_BRANCH_ID, 'branch_operator');
            $this->assign('pilot.delivery.dispatcher', 'branch_operator', 'NODE', self::TAJRISH_BRANCH_ID);
            $this->assign('pilot.delivery.dispatcher', 'dispatcher', 'NODE', self::TAJRISH_BRANCH_ID);

            foreach ([
                [self::PICKUP_DRIVER_ID, 'DRV-PICKUP-TBZ', 'راننده جمع‌آوری تبریز', 'pilot.pickup.driver', self::TBZ_BRANCH_ID, 'PICKUP'],
                [self::LINEHAUL_DRIVER_ID, 'DRV-LINEHAUL-TBZ-BR', 'راننده خطی شعبه تبریز', 'pilot.linehaul.driver', self::TBZ_BRANCH_ID, 'LINEHAUL'],
                [self::TBZ_HUB_LINEHAUL_DRIVER_ID, 'DRV-LINEHAUL-TBZ-HUB', 'راننده خطی هاب تبریز', null, self::TBZ_HUB_ID, 'LINEHAUL'],
                [self::THR_HUB_LINEHAUL_DRIVER_ID, 'DRV-LINEHAUL-THR-HUB', 'راننده خطی هاب تهران', null, self::THR_HUB_ID, 'LINEHAUL'],
                [self::DELIVERY_DRIVER_ID, 'DRV-DELIVERY-TAJRISH', 'راننده تحویل تجریش', 'pilot.delivery.driver', self::TAJRISH_BRANCH_ID, 'DELIVERY'],
            ] as [$id, $code, $name, $username, $node, $capability]) {
                RecordFixtureQuery::table('drivers')->updateOrInsert(['driver_id' => $id], ['hq_id' => self::HQ_ID, 'user_id' => $username === null ? null : self::ACTORS[$username][0], 'driver_code' => $code, 'display_name' => $name, 'home_node_id' => $node, 'operational_type' => $capability, 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                RecordFixtureQuery::table('driver_capabilities')->updateOrInsert(['driver_id' => $id, 'capability' => $capability], ['driver_capability_id' => $this->id("capability:{$id}:{$capability}"), 'hq_id' => self::HQ_ID, 'created_at' => now()]);
            }
            foreach ([[self::VEHICLE_ID, 'VEH-LINEHAUL-TBZ-BR', 'IR-15-پایلوت-01', self::TBZ_BRANCH_ID], [self::TBZ_HUB_VEHICLE_ID, 'VEH-LINEHAUL-TBZ-HUB', 'IR-15-پایلوت-02', self::TBZ_HUB_ID], [self::THR_HUB_VEHICLE_ID, 'VEH-LINEHAUL-THR-HUB', 'IR-15-پایلوت-03', self::THR_HUB_ID]] as [$id, $code, $plate, $node]) {
                RecordFixtureQuery::table('vehicles')->updateOrInsert(['vehicle_id' => $id], ['hq_id' => self::HQ_ID, 'vehicle_code' => $code, 'registration_number' => $plate, 'plate_number' => $plate, 'vehicle_type' => 'TRUCK', 'home_node_id' => $node, 'status' => 'ACTIVE', 'availability_status' => 'AVAILABLE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $pilotLegs = [[1, self::TBZ_BRANCH_ID, self::TBZ_HUB_ID], [2, self::TBZ_HUB_ID, self::THR_HUB_ID], [3, self::THR_HUB_ID, self::TAJRISH_BRANCH_ID]];
            $this->assertPilotRoute($pilotLegs);
            RecordFixtureQuery::table('route_definitions')->updateOrInsert(['route_definition_id' => self::ROUTE_ID], ['hq_id' => self::HQ_ID, 'route_code' => 'TBZ-THR-PILOT', 'route_title' => 'مسیر پایلوت تبریز به تجریش', 'status' => 'ACTIVE', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($pilotLegs as [$order, $origin, $destination]) {
                RecordFixtureQuery::table('route_definition_legs')->updateOrInsert(['route_definition_id' => self::ROUTE_ID, 'leg_order' => $order], ['route_definition_leg_id' => $this->id('route-leg:'.$order), 'hq_id' => self::HQ_ID, 'origin_node_id' => $origin, 'destination_node_id' => $destination, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
            }
            $routeVersionId = $this->id('route-version:1');
            RecordFixtureQuery::table('route_definition_versions')->updateOrInsert(['route_definition_version_id' => $routeVersionId], ['hq_id' => self::HQ_ID, 'route_definition_id' => self::ROUTE_ID, 'version_number' => 1, 'status' => 'PUBLISHED', 'purpose' => 'TRUNK', 'origin_node_id' => self::TBZ_BRANCH_ID, 'destination_node_id' => self::TAJRISH_BRANCH_ID, 'priority' => 100, 'version' => 1, 'created_by' => self::ACTORS['pilot.catalog.admin'][0], 'published_by' => self::ACTORS['pilot.catalog.admin'][0], 'published_at' => now(), 'content_digest' => hash('sha256', json_encode($pilotLegs, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($pilotLegs as [$order, $origin, $destination]) {
                RecordFixtureQuery::table('route_definition_version_legs')->updateOrInsert(['route_definition_version_id' => $routeVersionId, 'leg_order' => $order], ['route_definition_version_leg_id' => $this->id('route-version-leg:'.$order), 'hq_id' => self::HQ_ID, 'origin_node_id' => $origin, 'destination_node_id' => $destination, 'created_at' => now(), 'updated_at' => now()]);
            }
            RecordFixtureQuery::table('route_definitions')->where('route_definition_id', self::ROUTE_ID)->update(['published_version_id' => $routeVersionId]);

            $coveragePolicyId = $this->id('destination-gateway-policy');
            $coverageVersionId = $this->id('destination-gateway-version:1');
            RecordFixtureQuery::table('coverage_policies')->updateOrInsert(['coverage_policy_id' => $coveragePolicyId], ['hq_id' => self::HQ_ID, 'policy_code' => 'PILOT_DESTINATION_GATEWAY', 'policy_title' => 'پوشش مقصد آزمون تهران', 'published_version_id' => null, 'created_at' => now(), 'updated_at' => now()]);
            RecordFixtureQuery::table('coverage_policy_versions')->updateOrInsert(['coverage_policy_version_id' => $coverageVersionId], ['hq_id' => self::HQ_ID, 'coverage_policy_id' => $coveragePolicyId, 'version_number' => 1, 'status' => 'PUBLISHED', 'version' => 1, 'created_by' => self::ACTORS['pilot.catalog.admin'][0], 'published_by' => self::ACTORS['pilot.catalog.admin'][0], 'published_at' => now(), 'content_digest' => hash('sha256', 'pilot-destination-gateway-v1'), 'created_at' => now(), 'updated_at' => now()]);
            RecordFixtureQuery::table('coverage_rules')->updateOrInsert(['coverage_rule_id' => $this->id('destination-gateway-rule:tehran')], ['hq_id' => self::HQ_ID, 'coverage_policy_version_id' => $coverageVersionId, 'target' => 'DESTINATION_GATEWAY', 'target_node_id' => self::TAJRISH_BRANCH_ID, 'priority' => 100, 'criterion_type' => 'CITY', 'city_id' => GeographyIds::city('10866'), 'created_at' => now(), 'updated_at' => now()]);
            RecordFixtureQuery::table('coverage_policies')->where('coverage_policy_id', $coveragePolicyId)->update(['published_version_id' => $coverageVersionId]);
        });
    }

    /** @param array<int,array{0:int,1:string,2:string}> $legs */
    private function assertPilotRoute(array $legs): void
    {
        $orders = array_column($legs, 0);
        if ($orders !== range(1, count($legs)) || count($orders) !== count(array_unique($orders))) {
            throw new LogicException('Pilot route leg order must be unique and contiguous.');
        }
        $nodeIds = [];
        $visited = [];
        foreach ($legs as $index => [, $origin, $destination]) {
            if ($origin === $destination || ($index > 0 && $legs[$index - 1][2] !== $origin)) {
                throw new LogicException('Pilot route legs must form one directed chain.');
            }
            if ($index === 0) {
                $visited[$origin] = true;
            }
            if (isset($visited[$destination])) {
                throw new LogicException('Pilot route must not contain a cycle.');
            }
            $visited[$destination] = true;
            $nodeIds[] = $origin;
            $nodeIds[] = $destination;
        }
        $activeTenantNodes = RecordFixtureQuery::table('nodes')->where('hq_id', self::HQ_ID)->where('status', 'ACTIVE')->whereIn('node_id', array_values(array_unique($nodeIds)))->count();
        if ($activeTenantNodes !== count(array_unique($nodeIds))) {
            throw new LogicException('Every pilot route node must be active and belong to the pilot HQ.');
        }
    }

    private function seedCommercialConfiguration(): void
    {
        if (RecordFixtureQuery::table('tariff_families')->where(['hq_id' => self::HQ_ID, 'code' => 'UAT_TBZ_THR_SALES'])->exists()) {
            return;
        }
        $maker = $this->principal('pilot.catalog.admin');
        $checker = $this->principal('pilot.catalog.checker');
        $catalog = app(ServiceCatalogFixtures::class);
        $schedules = app(CommitmentScheduleFixtures::class);
        $pricing = app(PricingService::class);
        $validFrom = now()->subMinute()->utc()->toISOString();
        $type = $catalog->createIdentity($maker, 'service-types', ['code' => 'PILOT_INTERCITY', 'labels' => ['fa' => 'ارسال بین‌شهری پایلوت'], 'description' => null, 'definition' => ['classification' => 'INTERCITY'], 'valid_from' => $validFrom, 'valid_to' => null], $this->id('catalog:type:create'));
        $catalog->transition($checker, 'service-types', $type['service_type_version_id'], 'approve', $this->id('catalog:type:approve'));
        $catalog->transition($maker, 'service-types', $type['service_type_version_id'], 'publish', $this->id('catalog:type:publish'));
        $method = $catalog->createIdentity($maker, 'shipping-methods', ['code' => 'PILOT_GROUND', 'labels' => ['fa' => 'حمل زمینی پایلوت'], 'description' => null, 'definition' => ['mode' => 'ROAD'], 'valid_from' => $validFrom, 'valid_to' => null], $this->id('catalog:method:create'));
        $catalog->transition($checker, 'shipping-methods', $method['shipping_method_version_id'], 'approve', $this->id('catalog:method:approve'));
        $catalog->transition($maker, 'shipping-methods', $method['shipping_method_version_id'], 'publish', $this->id('catalog:method:publish'));
        $schedule = $schedules->create($maker, ['code' => 'PILOT_TBZ_PICKUP_72H', 'title' => 'پنجره جمع‌آوری پایلوت و تعهد ۷۲ ساعته', 'timezone' => 'Asia/Tehran', 'calendar_code' => 'IR_STANDARD', 'valid_from' => $validFrom, 'valid_to' => null, 'windows' => [['window_code' => 'PILOT_MORNING', 'window_type' => 'PICKUP', 'label_fa' => 'صبح', 'start_time' => '09:00', 'end_time' => '13:00', 'booking_cutoff_time' => '23:59', 'applicable_weekdays' => [1, 2, 3, 4, 5, 6, 7], 'day_offset' => 0, 'active' => true]], 'scopes' => [['scope_type' => 'NODE', 'node_id' => self::TBZ_BRANCH_ID]]], $this->id('schedule:create'));
        $schedules->transition($checker, $schedule['commitment_schedule_version_id'], 'approve', $this->id('schedule:approve'));
        $schedule = $schedules->transition($maker, $schedule['commitment_schedule_version_id'], 'publish', $this->id('schedule:publish'));
        $offering = $catalog->createIdentity($maker, 'offerings', ['code' => 'PILOT_TBZ_THR_72H', 'labels' => ['fa' => 'سرویس پایلوت تبریز به تهران'], 'description' => null, 'service_type_version_id' => $type['service_type_version_id'], 'shipping_method_version_id' => $method['shipping_method_version_id'], 'sla_policy' => ['commitment_type' => 'DURATION', 'duration_value' => 72, 'duration_unit' => 'HOUR'], 'availability_summary' => [], 'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [], 'commitment_binding' => ['commitment_schedule_version_id' => $schedule['commitment_schedule_version_id'], 'pickup_mode' => 'SELECTABLE_WINDOW', 'delivery_mode' => 'COMPUTED', 'duration_value' => 72, 'duration_unit' => 'HOUR', 'duration_anchor' => 'PICKUP_COMMITMENT_END'], 'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => self::HQ_ID, 'enabled' => true]], 'valid_from' => $validFrom, 'valid_to' => null], $this->id('offering:create'));
        $catalog->transition($checker, 'offerings', $offering['service_offering_version_id'], 'approve', $this->id('offering:approve'));
        $catalog->transition($maker, 'offerings', $offering['service_offering_version_id'], 'publish', $this->id('offering:publish'));
        $zones = $pricing->createZoneSet($maker, ['code' => 'PILOT_TBZ_THR_CITIES', 'purpose' => 'SALES', 'title' => 'شهرهای پایلوت تبریز و تهران', 'valid_from' => $validFrom, 'valid_to' => null, 'zones' => [['code' => 'ZONE_TBZ_CITY', 'title' => 'تبریز', 'members' => [['member_type' => 'CITY', 'city_id' => GeographyIds::city('10712')]]], ['code' => 'ZONE_THR_CITY', 'title' => 'تهران', 'members' => [['member_type' => 'CITY', 'city_id' => GeographyIds::city('10866')]]]]], $this->id('zones:create'));
        $pricing->transition($checker, 'zone-sets', $zones['zone_set_version_id'], 'approve', $this->id('zones:approve'));
        $pricing->transition($maker, 'zone-sets', $zones['zone_set_version_id'], 'publish', $this->id('zones:publish'));
        $zoneIds = collect($zones['zones'])->mapWithKeys(fn ($zone) => [$zone['code'] => $zone['pricing_zone_id']]);
        $charge = fn (string $code): string => (string) RecordFixtureQuery::table('pricing_charge_types')->where('code', $code)->value('charge_type_id');
        $tariff = $pricing->createTariff($maker, ['code' => 'UAT_TBZ_THR_SALES', 'purpose' => 'SALES', 'currency' => 'IRR', 'scope_type' => 'TENANT', 'scope_value' => self::HQ_ID, 'priority' => 100, 'zone_set_version_id' => $zones['zone_set_version_id'], 'valid_from' => $validFrom, 'valid_to' => null, 'volumetric_divisor' => 5000, 'weight_rounding_step_kg' => 0.5, 'rounding_mode' => 'STEP_UP', 'rules' => [
            ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $charge('BASE_FREIGHT'), 'origin_zone_id' => $zoneIds['ZONE_TBZ_CITY'], 'destination_zone_id' => $zoneIds['ZONE_THR_CITY'], 'calculation_method' => 'SLAB', 'basis' => 'BILLABLE_WEIGHT', 'range_from' => 0, 'range_to' => 10, 'fixed_amount' => 1100000, 'priority' => 10],
            ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $charge('INSURANCE'), 'origin_zone_id' => $zoneIds['ZONE_TBZ_CITY'], 'destination_zone_id' => $zoneIds['ZONE_THR_CITY'], 'calculation_method' => 'PERCENT', 'basis' => 'DECLARED_VALUE', 'percentage_bps' => 2, 'amount_rounding_mode' => 'CEIL', 'amount_rounding_step' => 10000, 'conditions' => ['insurance_enabled' => true], 'priority' => 20],
            ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $charge('TAX'), 'origin_zone_id' => $zoneIds['ZONE_TBZ_CITY'], 'destination_zone_id' => $zoneIds['ZONE_THR_CITY'], 'calculation_method' => 'PERCENT', 'basis' => 'SHIPMENT', 'percentage_bps' => 900, 'basis_charge_codes' => ['BASE_FREIGHT'], 'priority' => 100],
        ]], $this->id('tariff:create'));
        $pricing->transition($checker, 'tariffs', $tariff['tariff_version_id'], 'approve', $this->id('tariff:approve'));
        $pricing->transition($maker, 'tariffs', $tariff['tariff_version_id'], 'publish', $this->id('tariff:publish'));
    }

    private function operator(string $username, string $nodeId, string $role): void
    {
        $this->assign($username, $role, 'NODE', $nodeId);
        $this->assign($username, 'manifest_approver', 'NODE', $nodeId);
        $this->assign($username, 'dispatcher', 'NODE', $nodeId);
    }

    private function assign(string $username, string $roleCode, string $scope, ?string $scopeId): void
    {
        $userId = self::ACTORS[$username][0];
        $roleId = (string) RecordFixtureQuery::table('roles')->where(['owner_key' => 'GLOBAL', 'role_code' => $roleCode])->value('role_id');
        $slot = hash('sha256', "{$userId}|{$roleId}|{$scope}|".($scopeId ?? '-'));
        RecordFixtureQuery::table('user_role_assignments')->updateOrInsert(['active_slot' => $slot], ['assignment_id' => $this->id("assignment:{$slot}"), 'hq_id' => self::HQ_ID, 'user_id' => $userId, 'role_id' => $roleId, 'scope_type' => $scope, 'scope_id' => $scopeId, 'includes_descendants' => false, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function principal(string $username): AuthenticatedPrincipal
    {
        return new AuthenticatedPrincipal(self::ACTORS[$username][0], $this->id("session:{$username}"), self::HQ_ID, false);
    }

    private function id(string $key): string
    {
        return (string) (hexdec(substr(hash('sha256', 'chabok-operational-pilot:'.$key), 0, 7)) + 1);
    }
}
