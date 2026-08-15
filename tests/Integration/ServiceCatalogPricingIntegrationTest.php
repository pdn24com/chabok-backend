<?php

declare(strict_types=1);

namespace Tests\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Consignment\Application\ConsignmentService;
use Modules\Consignment\Application\PricingService as ConsignmentPricingService;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Geography\Domain\GeographyIds;
use Modules\Geography\Infrastructure\Database\Seeders\IranGeographySeeder;
use Modules\Pricing\Application\PricingService;
use Modules\Pricing\Infrastructure\Database\Seeders\PricingChargeTypeSeeder;
use Modules\ServiceCatalog\Application\ServiceCatalogService;

final class ServiceCatalogPricingIntegrationTest extends MySqlRedisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(IranGeographySeeder::class)->run();
    }

    public function test_catalog_versions_without_validity_bounds_publish_and_resolve_indefinitely(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant, $maker, $checker] = $this->administratorContext('UNBOUNDED-CATALOG');
        $catalog = $this->app->make(ServiceCatalogService::class);

        $type = $catalog->createIdentity($maker, 'service-types', [
            'code' => 'UNBOUNDED_TYPE', 'labels' => ['fa' => 'نوع خدمت همیشگی'], 'description' => null,
            'definition' => [], 'valid_from' => null, 'valid_to' => null,
        ], (string) Str::uuid());
        $this->assertSame(['valid' => true, 'errors' => []], $catalog->validateDraft($maker, 'service-types', $type['service_type_version_id']));
        $catalog->transition($maker, 'service-types', $type['service_type_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'service-types', $type['service_type_version_id'], 'publish', (string) Str::uuid());
        $publishedTypes = $catalog->listPublishedVersions($maker, 'service-types', ['page' => 1, 'page_size' => 100]);
        $this->assertSame(1, $publishedTypes->total());
        $this->assertSame($type['service_type_version_id'], $publishedTypes->items()[0]['service_type_version_id']);
        $this->assertSame('نوع خدمت همیشگی', $publishedTypes->items()[0]['labels']['fa']);

        $method = $catalog->createIdentity($maker, 'shipping-methods', [
            'code' => 'UNBOUNDED_METHOD', 'labels' => ['fa' => 'روش ارسال همیشگی'], 'description' => null,
            'definition' => [], 'valid_from' => null, 'valid_to' => null,
        ], (string) Str::uuid());
        $listedMethods = $catalog->listIdentities($maker, 'shipping-methods', ['page' => 1, 'page_size' => 25]);
        $this->assertSame('روش ارسال همیشگی', $listedMethods->items()[0]['labels']['fa']);
        $this->assertTrue($catalog->validateDraft($maker, 'shipping-methods', $method['shipping_method_version_id'])['valid']);
        $catalog->transition($checker, 'shipping-methods', $method['shipping_method_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'shipping-methods', $method['shipping_method_version_id'], 'publish', (string) Str::uuid());

        $offering = $catalog->createIdentity($maker, 'offerings', [
            'code' => 'UNBOUNDED_SERVICE', 'labels' => ['fa' => 'سرویس همیشگی'], 'description' => null,
            'service_type_version_id' => $type['service_type_version_id'],
            'shipping_method_version_id' => $method['shipping_method_version_id'],
            'sla_policy' => ['commitment_type' => 'DURATION', 'duration_value' => 24, 'duration_unit' => 'HOUR'],
            'availability_summary' => [], 'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'enabled' => true]],
            'valid_from' => null, 'valid_to' => null,
        ], (string) Str::uuid());
        $listedOfferings = $catalog->listIdentities($maker, 'offerings', ['page' => 1, 'page_size' => 25]);
        $this->assertSame('سرویس همیشگی', $listedOfferings->items()[0]['labels']['fa']);
        $this->assertTrue($catalog->validateDraft($maker, 'offerings', $offering['service_offering_version_id'])['valid']);
        $catalog->transition($checker, 'offerings', $offering['service_offering_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $offering['service_offering_version_id'], 'publish', (string) Str::uuid());

        $context = ['channel' => 'BRANCH', 'sender' => [], 'receiver' => [], 'parcels' => [], 'selected_option_version_ids' => []];
        $resolved = $catalog->resolve($maker, [...$context, 'as_of_timestamp' => '2046-01-01T00:00:00Z']);
        $this->assertSame('UNBOUNDED_SERVICE', $resolved[0]['offering_code']);
        $this->assertSame($offering['service_offering_version_id'], $catalog->validateSelection($maker, $offering['service_offering_id'], null, $context)['service_offering_version_id']);
        [, $foreignActor] = $this->administratorContext('UNBOUNDED-FOREIGN');
        $this->assertSame(0, $catalog->listPublishedVersions($foreignActor, 'service-types', [])->total());

        $successor = $catalog->cloneDraft($maker, 'service-types', $type['service_type_id'], (string) Str::uuid());
        $this->assertSame(1, $catalog->listPublishedVersions($maker, 'service-types', [])->total());
        $this->assertContains(
            'SERVICE_EFFECTIVE_INTERVAL_OVERLAP',
            array_column($catalog->validateDraft($maker, 'service-types', $successor['service_type_version_id'])['errors'], 'code'),
        );
    }

    public function test_published_catalog_and_tariff_produce_immutable_accepted_consignment_pricing(): void
    {
        config()->set('chabok.pricing.provider', 'internal');
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $this->app->make(PricingChargeTypeSeeder::class)->run();
        [$tenant, $maker, $checker, $nodeId] = $this->administratorContext();
        $catalog = $this->app->make(ServiceCatalogService::class);
        $pricing = $this->app->make(PricingService::class);
        $validFrom = now()->subMinute()->utc()->toISOString();

        $type = $catalog->createIdentity($maker, 'service-types', [
            'code' => 'EXPRESS', 'labels' => ['en' => 'Express'], 'description' => null,
            'definition' => ['classification' => 'EXPRESS'], 'valid_from' => $validFrom, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($checker, 'service-types', $type['service_type_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'service-types', $type['service_type_version_id'], 'publish', (string) Str::uuid());

        $method = $catalog->createIdentity($maker, 'shipping-methods', [
            'code' => 'GROUND', 'labels' => ['en' => 'Ground'], 'description' => null,
            'definition' => ['mode' => 'ROAD'], 'valid_from' => $validFrom, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($checker, 'shipping-methods', $method['shipping_method_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'shipping-methods', $method['shipping_method_version_id'], 'publish', (string) Str::uuid());

        $options = [];
        foreach (['PACKAGING' => 'Allowed packaging', 'SIGNATURE' => 'Required signature', 'DANGEROUS' => 'Forbidden dangerous goods', 'INSURANCE' => 'Conditional insurance'] as $code => $label) {
            $option = $catalog->createIdentity($maker, 'options', ['code' => $code, 'labels' => ['en' => $label], 'description' => null, 'definition' => [], 'valid_from' => $validFrom, 'valid_to' => null], (string) Str::uuid());
            $catalog->transition($checker, 'options', $option['service_option_version_id'], 'approve', (string) Str::uuid());
            $options[$code] = $catalog->transition($maker, 'options', $option['service_option_version_id'], 'publish', (string) Str::uuid());
        }

        $offering = $catalog->createIdentity($maker, 'offerings', [
            'code' => 'EXPRESS_GROUND', 'labels' => ['en' => 'Express Ground'], 'description' => null,
            'service_type_version_id' => $type['service_type_version_id'],
            'shipping_method_version_id' => $method['shipping_method_version_id'],
            'sla_policy' => ['commitment_type' => 'DURATION', 'duration_value' => 24, 'duration_unit' => 'HOUR'],
            'availability_summary' => [], 'option_rules' => [
                ['service_option_version_id' => $options['PACKAGING']['service_option_version_id'], 'compatibility' => 'ALLOWED'],
                ['service_option_version_id' => $options['SIGNATURE']['service_option_version_id'], 'compatibility' => 'REQUIRED'],
                ['service_option_version_id' => $options['DANGEROUS']['service_option_version_id'], 'compatibility' => 'FORBIDDEN'],
                ['service_option_version_id' => $options['INSURANCE']['service_option_version_id'], 'compatibility' => 'CONDITIONAL', 'condition' => ['fact_key' => 'insurance_enabled', 'operator' => 'EQ', 'expected_value' => true]],
            ], 'eligibility_rules' => [], 'coverage_references' => [],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'enabled' => true]],
            'valid_from' => $validFrom, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($checker, 'offerings', $offering['service_offering_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $offering['service_offering_version_id'], 'publish', (string) Str::uuid());

        $selectionContext = [...$this->consignmentDraft($type, $method, $offering), 'selected_option_version_ids' => []];
        foreach ([
            'SERVICE_OPTION_REQUIRED' => [],
            'SERVICE_OPTION_FORBIDDEN' => [$options['SIGNATURE']['service_option_version_id'], $options['DANGEROUS']['service_option_version_id']],
            'SERVICE_OPTION_CONDITION_NOT_MET' => [$options['SIGNATURE']['service_option_version_id'], $options['INSURANCE']['service_option_version_id']],
        ] as $reasonCode => $selectedOptions) {
            try {
                $catalog->validateSelection($maker, $offering['service_offering_id'], $offering['service_offering_version_id'], [...$selectionContext, 'selected_option_version_ids' => $selectedOptions]);
                $this->fail("{$reasonCode} must reject the selection.");
            } catch (ApiException $exception) {
                $this->assertContains($reasonCode, $exception->details['reason_codes']);
            }
        }

        $zoneSet = $pricing->createZoneSet($maker, [
            'code' => 'LOCAL', 'purpose' => 'SALES', 'title' => 'Local cities', 'valid_from' => $validFrom, 'valid_to' => null,
            'zones' => [
                ['code' => 'TEHRAN', 'title' => 'Tehran city', 'remote_area' => false, 'members' => [
                    ['member_type' => 'CITY', 'city_id' => GeographyIds::city('10866')],
                ]],
                ['code' => 'TEHRAN_PROVINCE', 'title' => 'Tehran province', 'remote_area' => false, 'members' => [
                    ['member_type' => 'PROVINCE', 'province_id' => GeographyIds::province('8')],
                ]],
            ],
        ], (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $zoneSet['zone_set_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $zoneSet['zone_set_version_id'], 'publish', (string) Str::uuid());
        $zoneId = (string) $zoneSet['zones'][0]['pricing_zone_id'];
        $baseChargeId = (string) DB::table('pricing_charge_types')->where('code', 'BASE_FREIGHT')->value('charge_type_id');
        $taxChargeId = (string) DB::table('pricing_charge_types')->where('code', 'TAX')->value('charge_type_id');
        $tariff = $pricing->createTariff($maker, [
            'code' => 'STANDARD_SALES', 'purpose' => 'SALES', 'currency' => 'IRR', 'scope_type' => 'TENANT',
            'scope_value' => $tenant['hq_id'], 'priority' => 100, 'zone_set_version_id' => $zoneSet['zone_set_version_id'],
            'valid_from' => $validFrom, 'valid_to' => null, 'volumetric_divisor' => 5000,
            'weight_rounding_step_kg' => 0.5, 'rounding_mode' => 'STEP_UP',
            'rules' => [
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $baseChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'FIXED', 'basis' => 'SHIPMENT', 'fixed_amount' => 10000, 'priority' => 10],
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $taxChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'PERCENT', 'basis' => 'SHIPMENT', 'percentage_bps' => 900, 'basis_charge_codes' => ['BASE_FREIGHT'], 'priority' => 100],
            ],
        ], (string) Str::uuid());
        $pricing->transition($maker, 'tariffs', $tariff['tariff_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'tariffs', $tariff['tariff_version_id'], 'publish', (string) Str::uuid());

        $draft = $this->consignmentDraft($type, $method, $offering);
        $draft['selected_option_version_ids'] = [$options['SIGNATURE']['service_option_version_id']];
        $quote = $this->app->make(ConsignmentPricingService::class)->calculate($maker, $nodeId, 'CREATE', $draft, null, null);
        $this->assertSame(10900, $quote['options'][0]['total_amount']);
        $this->assertCount(2, $quote['options'][0]['charge_lines']);
        $internalQuote = $pricing->quoteDetail($maker, $quote['options'][0]['internal_quote_id']);
        $this->assertSame('PER_PARCEL', $internalQuote['resolution_evidence']['weight']['weight_evidence']);
        $this->assertSame('CITY', $internalQuote['resolution_evidence']['origin']['member_type']);
        $this->assertSame(1.5, (float) $internalQuote['resolution_evidence']['weight']['billable_weight_kg']);
        $created = $this->app->make(ConsignmentService::class)->create($maker, $nodeId, [...$draft, 'accepted_quote' => [
            'quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id'],
        ]], (string) Str::uuid());

        $this->assertSame('LOCKED', $created['commercial_pricing_state']);
        $this->assertSame($offering['service_offering_version_id'], $created['service_offering_version_id']);
        $this->assertDatabaseHas('pricing_snapshots', ['object_id' => $created['consignment_id'], 'total_amount' => 10900]);
        $this->assertDatabaseCount('pricing_charge_lines', 2);
        $this->assertDatabaseHas('consignment_pricing_versions', ['consignment_id' => $created['consignment_id'], 'provider_code' => 'INTERNAL', 'total_amount' => 10900]);

        $bindingId = (string) DB::table('service_availability_bindings')->where('service_offering_version_id', $offering['service_offering_version_id'])->value('availability_binding_id');
        try {
            DB::table('service_availability_bindings')->where('availability_binding_id', $bindingId)->update(['enabled' => false]);
            $this->fail('Published offering children must be immutable.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('immutable published Service Offering child', $exception->getMessage());
        }
        $snapshotId = (string) DB::table('pricing_snapshots')->where('object_id', $created['consignment_id'])->value('pricing_snapshot_id');
        try {
            DB::table('pricing_charge_lines')->where('pricing_snapshot_id', $snapshotId)->update(['amount' => 1]);
            $this->fail('Accepted Charge Lines must be immutable.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('immutable accepted Pricing history', $exception->getMessage());
        }

        try {
            $catalog->updateDraft($maker, 'service-types', $type['service_type_version_id'], 1, ['labels' => ['en' => 'Mutated'], 'description' => null, 'definition' => [], 'valid_from' => $validFrom, 'valid_to' => null], (string) Str::uuid());
            $this->fail('Published versions must be immutable through the application boundary.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
        }
        $successor = $catalog->cloneDraft($maker, 'service-types', $type['service_type_id'], (string) Str::uuid());
        $this->assertSame(2, $successor['version_number']);
        $this->assertSame($type['service_type_version_id'], $successor['previous_version_id']);
        $successor = $catalog->updateDraft($maker, 'service-types', $successor['service_type_version_id'], 1, ['labels' => ['en' => 'Express successor'], 'description' => null, 'definition' => ['classification' => 'EXPRESS'], 'valid_from' => $validFrom, 'valid_to' => null], (string) Str::uuid());
        $this->assertContains('SERVICE_EFFECTIVE_INTERVAL_OVERLAP', array_column($catalog->validateDraft($maker, 'service-types', $successor['service_type_version_id'])['errors'], 'code'));
        $this->assertCount(2, $catalog->history($maker, 'service-types', $type['service_type_id']));

        $replayedQuote = $pricing->calculateQuote($maker, $internalQuote['normalized_input'], (string) $internalQuote['idempotency_key']);
        $this->assertSame($internalQuote['quote_id'], $replayedQuote['quote_id']);
        try {
            $pricing->calculateQuote($maker, [...$internalQuote['normalized_input'], 'declared_value_amount' => 100001], (string) $internalQuote['idempotency_key']);
            $this->fail('A pricing idempotency key must reject changed input.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::IdempotencyKeyReused, $exception->errorCode);
        }

        $acceptanceQuote = $pricing->calculateQuote($maker, $internalQuote['normalized_input'], 'pricing-acceptance-idempotency');
        $acceptedSnapshot = $pricing->acceptQuote($maker, $acceptanceQuote['quote_id'], 'CONSIGNMENT', $created['consignment_id'], $acceptanceQuote['input_fingerprint'], 'snapshot-acceptance-idempotency');
        $replayedSnapshot = $pricing->acceptQuote($maker, $acceptanceQuote['quote_id'], 'CONSIGNMENT', $created['consignment_id'], $acceptanceQuote['input_fingerprint'], 'snapshot-acceptance-idempotency');
        $this->assertSame($acceptedSnapshot['pricing_snapshot_id'], $replayedSnapshot['pricing_snapshot_id']);
        $this->assertSame($acceptanceQuote['total_amount'], $acceptedSnapshot['total_amount']);
        $this->assertCount(count($acceptanceQuote['lines']), $acceptedSnapshot['lines']);

        $expiryStart = CarbonImmutable::now('UTC');
        CarbonImmutable::setTestNow($expiryStart);
        config()->set('chabok.pricing.quote_ttl_seconds', 1);
        try {
            $expiringQuote = $pricing->calculateQuote($maker, $internalQuote['normalized_input'], 'pricing-expiry');
            try {
                $pricing->acceptQuote($maker, $expiringQuote['quote_id'], 'CONSIGNMENT', $created['consignment_id'], str_repeat('0', 64), 'pricing-tamper');
                $this->fail('A tampered fingerprint must be rejected.');
            } catch (ApiException $exception) {
                $this->assertSame(ApiErrorCode::PricingQuoteMismatch, $exception->errorCode);
            }
            CarbonImmutable::setTestNow($expiryStart->addSeconds(2));
            try {
                $pricing->acceptQuote($maker, $expiringQuote['quote_id'], 'CONSIGNMENT', $created['consignment_id'], $expiringQuote['input_fingerprint'], 'pricing-expired');
                $this->fail('An expired quote must be rejected.');
            } catch (ApiException $exception) {
                $this->assertSame(ApiErrorCode::PricingQuoteExpired, $exception->errorCode);
            }
        } finally {
            CarbonImmutable::setTestNow();
            config()->set('chabok.pricing.quote_ttl_seconds', 900);
        }

        $rejectableQuote = $pricing->calculateQuote($maker, $internalQuote['normalized_input'], 'pricing-rejection');
        $this->assertSame('REJECTED', $pricing->rejectQuote($maker, $rejectableQuote['quote_id'])['status']);

        $ambiguousTariff = $pricing->createTariff($maker, [
            'code' => 'AMBIGUOUS_SALES', 'purpose' => 'SALES', 'currency' => 'IRR', 'scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'priority' => 200,
            'zone_set_version_id' => $zoneSet['zone_set_version_id'], 'valid_from' => $validFrom, 'valid_to' => null, 'volumetric_divisor' => 5000, 'weight_rounding_step_kg' => 0.5, 'rounding_mode' => 'STEP_UP',
            'rules' => [
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $baseChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'PER_UNIT', 'basis' => 'BILLABLE_WEIGHT', 'range_from' => 0, 'range_to' => 2, 'unit_rate' => 100, 'priority' => 10],
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $baseChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'PER_UNIT', 'basis' => 'BILLABLE_WEIGHT', 'range_from' => 1, 'range_to' => 3, 'unit_rate' => 100, 'priority' => 10],
            ],
        ], (string) Str::uuid());
        $this->assertContains('PRICING_RULE_RANGE_OVERLAP', array_column($pricing->validateTariff($maker, $ambiguousTariff['tariff_version_id'])['errors'], 'code'));
    }

    public function test_draft_concurrency_and_cross_tenant_visibility_fail_closed(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant, $maker] = $this->administratorContext('CATALOG-A');
        $catalog = $this->app->make(ServiceCatalogService::class);
        $draft = $catalog->createIdentity($maker, 'options', ['code' => 'SIGNATURE', 'labels' => ['en' => 'Signature'], 'description' => null, 'definition' => [], 'valid_from' => null, 'valid_to' => null], (string) Str::uuid());
        $catalog->updateDraft($maker, 'options', $draft['service_option_version_id'], 1, ['labels' => ['en' => 'Signature required'], 'description' => null, 'definition' => [], 'valid_from' => null, 'valid_to' => null], (string) Str::uuid());
        try {
            $catalog->updateDraft($maker, 'options', $draft['service_option_version_id'], 1, ['labels' => ['en' => 'Stale'], 'description' => null, 'definition' => [], 'valid_from' => null, 'valid_to' => null], (string) Str::uuid());
            $this->fail('A stale draft lock version must fail.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::VersionConflict, $exception->errorCode);
        }

        [, $foreign] = $this->administratorContext('CATALOG-B');
        $this->assertSame([], $catalog->listIdentities($foreign, 'options', ['page' => 1, 'page_size' => 25])->items());
        $this->assertDatabaseHas('service_options', ['hq_id' => $tenant['hq_id'], 'code' => 'SIGNATURE']);

        $readOnlyUser = $this->user($tenant['hq_id'], 'catalog-read-only');
        $readOnlyRoleId = (string) DB::table('roles')->where('role_code', 'branch_read_only')->value('role_id');
        DB::table('user_role_assignments')->insert(['assignment_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'], 'user_id' => $readOnlyUser['user_id'], 'role_id' => $readOnlyRoleId, 'scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false, 'status' => 'ACTIVE', 'active_slot' => hash('sha256', "{$readOnlyUser['user_id']}|{$readOnlyRoleId}|TENANT|-"), 'created_at' => now(), 'updated_at' => now()]);
        try {
            $catalog->listIdentities(new AuthenticatedPrincipal($readOnlyUser['user_id'], (string) Str::uuid(), $tenant['hq_id'], false), 'options', []);
            $this->fail('A role without Catalog permission must be denied.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PermissionDenied, $exception->errorCode);
        }

        $pricing = $this->app->make(PricingService::class);
        $ambiguousZoneSet = $pricing->createZoneSet($maker, [
            'code' => 'AMBIGUOUS', 'purpose' => 'SALES', 'title' => 'Ambiguous city mapping', 'valid_from' => now()->subMinute()->utc()->toISOString(), 'valid_to' => null,
            'zones' => [
                ['code' => 'A', 'title' => 'A', 'members' => [['member_type' => 'CITY', 'city_id' => GeographyIds::city('10866')]]],
                ['code' => 'B', 'title' => 'B', 'members' => [['member_type' => 'CITY', 'city_id' => GeographyIds::city('10866')]]],
            ],
        ], (string) Str::uuid());
        $this->assertContains('PRICING_ZONE_AMBIGUOUS', array_column($pricing->validateZoneSet($maker, $ambiguousZoneSet['zone_set_version_id'])['errors'], 'code'));
    }

    public function test_admin_http_boundaries_reject_malformed_nested_configuration(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $this->administratorContext('BOUNDARY');
        $token = $this->login('boundary-maker')['token'];

        $this->withToken($token)->postJson('/api/v1/admin/service-catalog/offerings', [
            'code' => 'INVALID_OFFERING', 'labels' => ['en' => 'Invalid'], 'service_type_version_id' => (string) Str::uuid(), 'shipping_method_version_id' => (string) Str::uuid(),
            'sla_policy' => [], 'option_rules' => [['service_option_version_id' => (string) Str::uuid()]], 'availability_bindings' => [['scope_type' => 'TENANT']],
        ])->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->withToken($token)->postJson('/api/v1/admin/pricing/zone-sets', [
            'code' => 'INVALID_ZONE', 'purpose' => 'SALES', 'title' => 'Invalid', 'zones' => [['code' => 'TEHRAN', 'title' => 'Tehran', 'members' => [['reference_value' => 'Tehran']]]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');

        $this->withToken($token)->postJson('/api/v1/admin/pricing/zone-sets', [
            'code' => 'CANONICAL_ZONE', 'purpose' => 'SALES', 'title' => 'Canonical geography',
            'zones' => [[
                'code' => 'TEHRAN', 'title' => 'Tehran',
                'members' => [
                    ['member_type' => 'CITY', 'city_id' => GeographyIds::city('10866')],
                    ['member_type' => 'PROVINCE', 'province_id' => GeographyIds::province('8')],
                ],
            ]],
        ])->assertCreated();
        $this->assertDatabaseHas('pricing_zone_members', [
            'member_type' => 'CITY', 'city_id' => GeographyIds::city('10866'), 'precedence' => 200,
        ]);
        $this->assertDatabaseHas('pricing_zone_members', [
            'member_type' => 'PROVINCE', 'province_id' => GeographyIds::province('8'), 'precedence' => 100,
        ]);

        $this->withToken($token)->postJson('/api/v1/admin/pricing/tariff-families', [
            'code' => 'INVALID_TARIFF', 'purpose' => 'SALES', 'currency' => 'IRR', 'zone_set_version_id' => (string) Str::uuid(),
            'rules' => [['service_offering_version_id' => (string) Str::uuid(), 'charge_type_id' => (string) Str::uuid()]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    /** @return array{array<string,mixed>,AuthenticatedPrincipal,AuthenticatedPrincipal,string} */
    private function administratorContext(string $code = 'CATALOG-PRICE'): array
    {
        $tenant = $this->tenant($code); $makerUser = $this->user($tenant['hq_id'], strtolower($code).'-maker'); $checkerUser = $this->user($tenant['hq_id'], strtolower($code).'-checker');
        foreach (['Foundation', 'IAM', 'Consignment', 'Parcel', 'Manifest', 'Audit', 'ServiceCatalog', 'Pricing'] as $module) DB::table('tenant_module_entitlements')->insert(['entitlement_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'], 'module_code' => $module, 'status' => 'ENABLED', 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $roleId = (string) DB::table('roles')->where('role_code', 'hq_admin')->value('role_id');
        foreach ([$makerUser, $checkerUser] as $user) DB::table('user_role_assignments')->insert(['assignment_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'], 'user_id' => $user['user_id'], 'role_id' => $roleId, 'scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false, 'status' => 'ACTIVE', 'active_slot' => hash('sha256', "{$user['user_id']}|{$roleId}|TENANT|-"), 'created_at' => now(), 'updated_at' => now()]);
        $branchManagerRoleId = (string) DB::table('roles')->where('role_code', 'branch_manager')->value('role_id');
        DB::table('user_role_assignments')->insert(['assignment_id' => (string) Str::uuid(), 'hq_id' => $tenant['hq_id'], 'user_id' => $makerUser['user_id'], 'role_id' => $branchManagerRoleId, 'scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false, 'status' => 'ACTIVE', 'active_slot' => hash('sha256', "{$makerUser['user_id']}|{$branchManagerRoleId}|TENANT|-"), 'created_at' => now(), 'updated_at' => now()]);
        $areaId = (string) Str::uuid(); DB::table('areas')->insert(['area_id' => $areaId, 'hq_id' => $tenant['hq_id'], 'area_title' => 'Pricing area', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $nodeId = (string) Str::uuid(); DB::table('nodes')->insert(['node_id' => $nodeId, 'hq_id' => $tenant['hq_id'], 'area_id' => $areaId, 'node_code' => $code, 'node_title' => 'Pricing branch', 'node_type' => 'BRANCH', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        return [$tenant, new AuthenticatedPrincipal($makerUser['user_id'], (string) Str::uuid(), $tenant['hq_id'], false), new AuthenticatedPrincipal($checkerUser['user_id'], (string) Str::uuid(), $tenant['hq_id'], false), $nodeId];
    }

    /** @param array<string,mixed> $type @param array<string,mixed> $method @param array<string,mixed> $offering @return array<string,mixed> */
    private function consignmentDraft(array $type, array $method, array $offering): array
    {
        return ['sender' => ['contact_name' => 'Sender', 'mobile' => '09120000001', 'address_text' => 'Tehran', 'city_id' => GeographyIds::city('10866'), 'state' => 'Tehran', 'city' => 'Tehran'], 'receiver' => ['contact_name' => 'Receiver', 'mobile' => '09120000002', 'address_text' => 'Tehran', 'city_id' => GeographyIds::city('10866'), 'state' => 'Tehran', 'city' => 'Tehran'], 'service_type_id' => $type['service_type_id'], 'shipping_method_id' => $method['shipping_method_id'], 'service_offering_id' => $offering['service_offering_id'], 'service_offering_version_id' => $offering['service_offering_version_id'], 'selected_option_version_ids' => [], 'pickup_commitment_at' => now()->addHour()->utc()->toISOString(), 'delivery_commitment_at' => now()->addDay()->utc()->toISOString(), 'weight_kg' => 1.1, 'width_cm' => 10, 'length_cm' => 10, 'height_cm' => 10, 'declared_value_amount' => 100000, 'insurance_enabled' => false, 'insurance_value_amount' => null, 'cod_enabled' => false, 'cod_amount' => null, 'payer' => 'SENDER', 'payment_method' => 'CASH', 'parcels' => [['weight_kg' => 1.1, 'width_cm' => 10, 'length_cm' => 10, 'height_cm' => 10]]];
    }
}
