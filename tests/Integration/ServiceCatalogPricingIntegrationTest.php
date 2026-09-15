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
use Modules\ServiceCatalog\Application\CommitmentScheduleService;
use Modules\ServiceCatalog\Application\ServiceCatalogService;
use Modules\ServiceCatalog\Application\CatalogRecordService;

final class ServiceCatalogPricingIntegrationTest extends MySqlRedisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(IranGeographySeeder::class)->run();
    }

    public function test_schedule_owned_destination_policy_uses_current_zones_and_keeps_old_evidence(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant,$maker,$checker]=$this->administratorContext('SLA-ZONES');
        [, $foreign]=$this->administratorContext('SLA-FOREIGN');
        $pricing=$this->app->make(PricingService::class);
        $zone=$pricing->createZoneSet($maker,['code'=>'SLA_ZONE','purpose'=>'SALES','title'=>'مقصدها','valid_from'=>now()->subDay()->toISOString(),'valid_to'=>null,'zones'=>[['code'=>'TEHRAN','title'=>'تهران','remote_area'=>false,'members'=>[['member_type'=>'CITY','city_id'=>GeographyIds::city('10866')]]]]],(string)Str::uuid());
        $pricing->transition($maker,'zone-sets',$zone['zone_set_version_id'],'approve',(string)Str::uuid());
        $pricing->transition($maker,'zone-sets',$zone['zone_set_version_id'],'publish',(string)Str::uuid());
        $records=$this->app->make(CatalogRecordService::class);
        $type=$records->save($maker,'service-types',null,['labels'=>['fa'=>'عادی'],'definition'=>[]],(string)Str::uuid());
        $method=$records->save($maker,'shipping-methods',null,['labels'=>['fa'=>'زمینی'],'definition'=>[]],(string)Str::uuid());
        $policy=\Modules\ServiceCatalog\Application\SchedulePolicy::fromBinding(['pickup_mode'=>'NONE','delivery_mode'=>'COMPUTED','duration_value'=>72,'duration_unit'=>'HOUR','duration_anchor'=>'CONSIGNMENT_CREATED']);
        $policy['zone_set_id']=$zone['pricing_zone_set_id'];
        $policy['destination_rules']=[['id'=>(string)Str::uuid(),'destination_zone_code'=>'TEHRAN','origin_zone_code'=>null,'policy'=>[...$policy['delivery'],'duration_value'=>24]]];
        $input=['title'=>'تعهد مقصد','windows'=>[],'scopes'=>[['scope_type'=>'HQ']],'commitment_policy'=>$policy];
        $schedule=$records->save($maker,'commitment-schedules',null,$input,(string)Str::uuid());
        $offering=$records->save($maker,'offerings',null,['labels'=>['fa'=>'حمل'],'service_type_version_id'=>$type['service_type_version_id'],'shipping_method_version_id'=>$method['shipping_method_version_id'],'option_rules'=>[],'eligibility_rules'=>[],'coverage_references'=>[],'availability_bindings'=>[['scope_type'=>'TENANT','enabled'=>true]],'commitment_binding'=>['commitment_schedule_version_id'=>$schedule['commitment_schedule_version_id'],'pickup_mode'=>'NONE','delivery_mode'=>'NONE']],(string)Str::uuid());
        $service=$this->app->make(CommitmentScheduleService::class);
        $context=['acceptance_at'=>'2026-09-14T09:00:00Z','receiver'=>['city_id'=>GeographyIds::city('10866')]];
        $first=$service->resolveForOffering($offering['service_offering_version_id'],$context);
        $this->assertSame('2026-09-15T09:00:00.000000Z',$first['delivery']['computed_at']);
        $this->assertSame($policy['destination_rules'][0]['id'],$first['selected_rule_id']);
        $this->assertSame($zone['zone_set_version_id'],$first['destination_zone']['zone_set_version_id']);
        $fallback=$service->resolveForOffering($offering['service_offering_version_id'],[...$context,'receiver'=>[]]);
        $this->assertSame('2026-09-17T09:00:00.000000Z',$fallback['delivery']['computed_at']);
        $this->assertNull($fallback['selected_rule_id']);
        $old=(array)DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id',$schedule['commitment_schedule_version_id'])->first();
        $input['commitment_policy']['destination_rules'][0]['policy']['duration_value']=48;
        $changed=$records->save($maker,'commitment-schedules',$schedule['commitment_schedule_id'],[...$input,'expected_version'=>$schedule['lock_version']],(string)Str::uuid());
        $second=$service->resolveForOffering($offering['service_offering_version_id'],$context);
        $this->assertSame('2026-09-16T09:00:00.000000Z',$second['delivery']['computed_at']);
        $this->assertSame('2026-09-15T09:00:00.000000Z',$first['delivery']['computed_at']);
        $this->assertSame($old['commitment_policy'],DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id',$schedule['commitment_schedule_version_id'])->value('commitment_policy'));
        $reader=$this->app->make(\Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver::class);
        try { $reader->group((string)$foreign->hqId,$zone['pricing_zone_set_id']);$this->fail('Cross tenant zone must fail'); } catch(ApiException $e) {$this->assertSame(422,$e->httpStatus);}
        $duplicate=$input['commitment_policy'];$duplicate['destination_rules'][]=[...$duplicate['destination_rules'][0],'id'=>(string)Str::uuid()];
        try { $records->save($maker,'commitment-schedules',$schedule['commitment_schedule_id'],[...$input,'commitment_policy'=>$duplicate,'expected_version'=>$changed['lock_version']],(string)Str::uuid());$this->fail('Ambiguous destination rules must fail'); } catch(\Illuminate\Validation\ValidationException $e) {$this->assertArrayHasKey('destination_rules.0.destination_zone_code',$e->errors());}
        $this->assertSame($changed['commitment_schedule_version_id'],$records->detail($maker,'commitment-schedules',$schedule['commitment_schedule_id'])['commitment_schedule_version_id']);
        $successor=$pricing->cloneDraft($maker,'zone-sets',$zone['pricing_zone_set_id'],(string)Str::uuid());
        $successor=$pricing->updateZoneVersion($maker,$successor['zone_set_version_id'],[...$successor,'valid_from'=>now()->subMinute()->toISOString(),'expected_version'=>$successor['lock_version']]);
        $pricing->transition($maker,'zone-sets',$successor['zone_set_version_id'],'approve',(string)Str::uuid());
        $pricing->transition($maker,'zone-sets',$successor['zone_set_version_id'],'publish',(string)Str::uuid());
        $third=$service->resolveForOffering($offering['service_offering_version_id'],$context);
        $this->assertSame($successor['zone_set_version_id'],$third['destination_zone']['zone_set_version_id']);
        $this->assertSame($zone['zone_set_version_id'],$first['destination_zone']['zone_set_version_id']);
        try { \Modules\ServiceCatalog\Application\CurrentCatalog::assertQuoteCurrent((object)['hq_id'=>$tenant['hq_id'],'service_offering_version_id'=>$offering['service_offering_version_id'],'resolution_evidence'=>json_encode(['service'=>['commitment'=>$second]])]); $this->fail('Changed SLA zones require a new quote'); } catch(ApiException $e) { $this->assertSame('CATALOG_CHANGED',$e->details['reason_code']); }
        $specificOnly=$input['commitment_policy']; $specificOnly['delivery']=null;
        $specific=$records->save($maker,'commitment-schedules',$schedule['commitment_schedule_id'],[...$input,'commitment_policy'=>$specificOnly,'expected_version'=>$changed['lock_version']],(string)Str::uuid());
        $this->assertNull($specific['commitment_policy']['delivery']);
        $this->assertTrue($service->resolveForOffering($offering['service_offering_version_id'],$context)['eligible']);
        $uncovered=$service->resolveForOffering($offering['service_offering_version_id'],[...$context,'receiver'=>[]]);
        $this->assertFalse($uncovered['eligible']);
        $this->assertSame('DELIVERY_COMMITMENT_UNCONFIGURED',$uncovered['reason_code']);
        $incomplete=$specificOnly; $incomplete['destination_rules']=[];
        try { $records->save($maker,'commitment-schedules',$schedule['commitment_schedule_id'],[...$input,'commitment_policy'=>$incomplete,'expected_version'=>$specific['lock_version']],(string)Str::uuid()); $this->fail('Uncovered zones require a default'); }
        catch(ApiException $e) { $this->assertSame(422,$e->httpStatus); }
        $this->assertSame($specific['commitment_schedule_version_id'],$records->detail($maker,'commitment-schedules',$schedule['commitment_schedule_id'])['commitment_schedule_version_id']);
        try { DB::table('commitment_schedule_versions')->where('commitment_schedule_version_id',$changed['commitment_schedule_version_id'])->update(['status'=>'SUPERSEDED','commitment_policy'=>'{}']);$this->fail('Published SLA must be immutable'); } catch(QueryException $e) {$this->assertStringContainsString('immutable published commitment',$e->getMessage());}
    }

    public function test_current_catalog_http_endpoints_accept_stable_links_and_replay_creation(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant] = $this->administratorContext('CURRENT-HTTP');
        $token = $this->login('current-http-maker')['token'];
        $base = '/api/v1/admin/service-catalog/records/';
        $key = (string) Str::uuid();
        $input = ['labels' => ['fa' => 'سرویس عادی'], 'definition' => [], 'valid_from'=>'2099-01-01T00:00:00Z', 'valid_to'=>'2099-02-01T00:00:00Z'];
        $type = $this->withToken($token)->postJson($base.'service-types', $input, ['Idempotency-Key' => $key])->assertCreated()->json('data');
        $this->withToken($token)->postJson($base.'service-types', $input, ['Idempotency-Key' => $key])->assertCreated()->assertJsonPath('data.service_type_id', $type['service_type_id']);
        $this->assertDatabaseCount('service_types', 1);
        $this->assertNull($type['valid_from']); $this->assertNull($type['valid_to']);
        $method = $this->withToken($token)->postJson($base.'shipping-methods', $input, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');
        $offering = ['labels' => ['fa' => 'حمل عادی'], 'service_type_id' => $type['service_type_id'], 'shipping_method_id' => $method['shipping_method_id'], 'sla_policy' => ['duration_value' => 24, 'duration_unit' => 'HOUR'], 'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [], 'availability_bindings' => [['scope_type' => 'TENANT', 'enabled' => true]]];
        $created = $this->withToken($token)->postJson($base.'offerings', $offering, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data');
        $this->assertSame($type['service_type_id'], $created['service_type_id']);
        $url = $base.'offerings/'.$created['service_offering_id'];
        $this->withToken($token)->getJson($url)->assertOk()->assertJsonPath('data.labels.fa', 'حمل عادی');
        $changed = $this->withToken($token)->putJson($url, [...$offering, 'labels' => ['fa' => 'عنوان ویرایش‌شده'], 'expected_version' => $created['lock_version']], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->json('data');
        $this->withToken($token)->patchJson($url.'/status', ['active' => false, 'expected_version' => $changed['lock_version']])->assertOk()->assertJsonPath('data.status', 'INACTIVE');
        $this->withToken($token)->postJson($base.'options', ['code' => '12345', 'labels' => ['fa' => 'کد نادرست']], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
    }

    public function test_direct_catalog_edits_preserve_references_and_enforce_authorization_and_concurrency(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant, $maker] = $this->administratorContext('DIRECT-EDITS');
        [, $foreign] = $this->administratorContext('DIRECT-FOREIGN');
        $records = $this->app->make(CatalogRecordService::class);
        foreach (['service-types' => 'service_type', 'shipping-methods' => 'shipping_method', 'options' => 'service_option'] as $resource => $prefix) {
            $input = ['labels' => ['fa' => 'عنوان اولیه'], 'definition' => [], 'valid_from' => null, 'valid_to' => null];
            $created = $records->save($maker, $resource, null, $input, (string) Str::uuid());
            $id = $created[$prefix.'_id'];
            $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $created['code']);
            $updatedInput = [...$input, 'labels' => ['fa' => 'عنوان جدید'], 'expected_version' => $created['lock_version']];
            $changed = $records->save($maker, $resource, $id, $updatedInput, (string) Str::uuid());
            $this->assertSame($id, $changed[$prefix.'_id']);
            $this->assertSame($changed[$prefix.'_version_id'], \Modules\ServiceCatalog\Application\CurrentCatalog::resolve($resource, $created[$prefix.'_version_id'], $tenant['hq_id'])[$prefix.'_version_id']);
            $this->assertSame($changed, $records->save($maker, $resource, $id, $updatedInput, (string) Str::uuid()));
            try { $records->save($maker, $resource, $id, [...$updatedInput, 'description' => 'stale'], (string) Str::uuid()); $this->fail('Stale changes must conflict.'); }
            catch (ApiException $error) { $this->assertSame(ApiErrorCode::VersionConflict, $error->errorCode); }
            try { $records->save($foreign, $resource, $id, $updatedInput, (string) Str::uuid()); $this->fail('Cross tenant edits must fail.'); }
            catch (ApiException $error) { $this->assertSame(ApiErrorCode::ResourceNotFound, $error->errorCode); }
            $inactive = $records->setActive($maker, $resource, $id, false, $changed['lock_version'], (string) Str::uuid());
            $this->assertSame(0, $this->app->make(ServiceCatalogService::class)->listPublishedVersions($maker, $resource, [])->total());
            $this->assertSame('INACTIVE', $inactive['status']);
            $this->assertSame('عنوان جدید', $records->detail($maker, $resource, $id)['labels']['fa']);
        }
        $this->assertTrue(DB::table('audit_events')->where('action_key', 'SERVICE_CATALOG_RECORD_SAVED')->exists());
        $authorization = $this->createStub(\Modules\Foundation\Application\Contracts\AuthorizationContextResolver::class);
        $authorization->method('resolve')->willReturn(['module_entitlements' => [['module_code' => 'ServiceCatalog', 'status' => 'ENABLED']], 'permissions' => ['service_catalog.view', 'service_catalog.manage_draft']]);
        $restricted = new CatalogRecordService($this->app->make(ServiceCatalogService::class), $this->app->make(CommitmentScheduleService::class), $authorization, $this->app->make(\Modules\Foundation\Application\Contracts\AuditWriter::class), $this->app->make(\Modules\Foundation\Application\Contracts\OutboxWriter::class));
        try { $restricted->save($maker, 'options', null, ['labels' => ['fa' => 'غیرمجاز']], (string) Str::uuid()); $this->fail('Draft-only grant cannot apply live configuration.'); }
        catch (ApiException $error) { $this->assertSame(ApiErrorCode::PermissionDenied, $error->errorCode); }
    }

    public function test_catalog_versions_without_validity_bounds_publish_and_resolve_indefinitely(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant, $maker, $checker] = $this->administratorContext('UNBOUNDED-CATALOG');
        $catalog = $this->app->make(ServiceCatalogService::class);

        $type = $catalog->createIdentity($maker, 'service-types', [
            'code' => 'UNBOUNDED_TYPE', 'labels' => ['fa' => 'نوع خدمت همیشگی'], 'description' => null,
            'definition' => [], 'valid_from' => '2000-01-01T00:00:00Z', 'valid_to' => '2001-01-01T00:00:00Z',
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
            'definition' => [], 'valid_from' => '2000-01-01T00:00:00Z', 'valid_to' => '2001-01-01T00:00:00Z',
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
            'valid_from' => '2000-01-01T00:00:00Z', 'valid_to' => '2001-01-01T00:00:00Z',
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

        $offeringSuccessor = $catalog->cloneDraft($maker, 'offerings', $offering['service_offering_id'], (string) Str::uuid());
        $this->assertSame($offering['service_offering_version_id'], $offeringSuccessor['previous_version_id']);
        $this->assertNotSame($offeringSuccessor['service_offering_version_id'], $offeringSuccessor['previous_version_id']);
        $this->assertDatabaseHas('service_availability_bindings', [
            'service_offering_version_id' => $offeringSuccessor['service_offering_version_id'],
            'scope_type' => 'TENANT',
            'scope_value' => $tenant['hq_id'],
        ]);
    }

    public function test_published_catalog_and_tariff_produce_immutable_accepted_consignment_pricing(): void
    {
        config()->set('chabok.pricing.provider', 'internal');
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $this->app->make(PricingChargeTypeSeeder::class)->run();
        [$tenant, $maker, $checker, $nodeId] = $this->administratorContext();
        DB::table('consignment_number_ranges')->insert([
            'range_id' => (string) Str::uuid(),
            'hq_id' => $tenant['hq_id'],
            'title' => 'Pricing snapshot integration inventory',
            'numeric_prefix' => '654321',
            'total_length' => 12,
            'serial_width' => 6,
            'serial_start' => '000001',
            'serial_end' => '999999',
            'next_serial' => '000001',
            'first_number' => '654321000001',
            'last_number' => '654321999999',
            'status' => 'AVAILABLE',
            'created_by' => $maker->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
        $scheduleService = $this->app->make(CommitmentScheduleService::class);
        $schedule = $scheduleService->create($maker, [
            'code' => 'EXPRESS_WINDOWS', 'title' => 'پنجره‌های اکسپرس', 'timezone' => 'Asia/Tehran', 'calendar_code' => 'IR_STANDARD',
            'valid_from' => $validFrom, 'valid_to' => null,
            'windows' => [['risk_threshold_minutes' => 45, 'window_code' => 'MORNING', 'window_type' => 'PICKUP', 'label_fa' => 'صبح', 'start_time' => '09:00', 'end_time' => '13:00', 'booking_cutoff_time' => '23:59', 'applicable_weekdays' => [1, 2, 3, 4, 5, 6, 7], 'day_offset' => 0, 'active' => true]],
            'scopes' => [['scope_type' => 'NODE', 'node_id' => $nodeId]],
        ], (string) Str::uuid());
        self::assertSame(45, (int) $scheduleService->versionDetail($checker, $schedule['commitment_schedule_version_id'])['windows'][0]['risk_threshold_minutes']);
        $scheduleService->transition($checker, $schedule['commitment_schedule_version_id'], 'approve', (string) Str::uuid());
        $schedule = $scheduleService->transition($maker, $schedule['commitment_schedule_version_id'], 'publish', (string) Str::uuid());

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
            'commitment_binding' => ['commitment_schedule_version_id' => $schedule['commitment_schedule_version_id'], 'pickup_mode' => 'SELECTABLE_WINDOW', 'delivery_mode' => 'COMPUTED', 'duration_value' => 72, 'duration_unit' => 'HOUR', 'duration_anchor' => 'PICKUP_COMMITMENT_END'],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'enabled' => true]],
            'valid_from' => $validFrom, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($checker, 'offerings', $offering['service_offering_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $offering['service_offering_version_id'], 'publish', (string) Str::uuid());

        $otherSchedule = $scheduleService->create($maker, [
            'code' => 'OTHER_WINDOWS', 'title' => 'پنجره‌های خدمت دیگر', 'timezone' => 'Asia/Tehran', 'calendar_code' => 'IR_STANDARD',
            'valid_from' => $validFrom, 'valid_to' => null,
            'windows' => [['window_code' => 'AFTERNOON', 'window_type' => 'PICKUP', 'label_fa' => 'بعدازظهر', 'start_time' => '14:00', 'end_time' => '18:00', 'booking_cutoff_time' => '23:59', 'applicable_weekdays' => [1, 2, 3, 4, 5, 6, 7], 'day_offset' => 0, 'active' => true]],
            'scopes' => [['scope_type' => 'NODE', 'node_id' => $nodeId]],
        ], (string) Str::uuid());
        $scheduleService->transition($checker, $otherSchedule['commitment_schedule_version_id'], 'approve', (string) Str::uuid());
        $otherSchedule = $scheduleService->transition($maker, $otherSchedule['commitment_schedule_version_id'], 'publish', (string) Str::uuid());

        $otherOffering = $catalog->createIdentity($maker, 'offerings', [
            'code' => 'OTHER_GROUND', 'labels' => ['en' => 'Other Ground'], 'description' => null,
            'service_type_version_id' => $type['service_type_version_id'],
            'shipping_method_version_id' => $method['shipping_method_version_id'],
            'sla_policy' => ['commitment_type' => 'DURATION', 'duration_value' => 24, 'duration_unit' => 'HOUR'],
            'availability_summary' => [], 'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [],
            'commitment_binding' => ['commitment_schedule_version_id' => $otherSchedule['commitment_schedule_version_id'], 'pickup_mode' => 'SELECTABLE_WINDOW', 'delivery_mode' => 'COMPUTED', 'duration_value' => 24, 'duration_unit' => 'HOUR', 'duration_anchor' => 'PICKUP_COMMITMENT_END'],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'enabled' => true]],
            'valid_from' => $validFrom, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($checker, 'offerings', $otherOffering['service_offering_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $otherOffering['service_offering_version_id'], 'publish', (string) Str::uuid());

        $preWindowContext = $this->consignmentDraft($type, $method, $offering);
        unset($preWindowContext['pickup_service_date'], $preWindowContext['pickup_window_code']);
        $preWindowContext['selected_option_version_ids'] = [$options['SIGNATURE']['service_option_version_id']];
        $preWindowOfferings = $catalog->resolve($maker, $preWindowContext);
        $this->assertContains('EXPRESS_GROUND', array_column($preWindowOfferings, 'offering_code'));
        $preview = $catalog->commitmentPreview($maker, $offering['service_offering_id'], $preWindowContext);
        $this->assertSame('MORNING', $preview['pickup']['windows'][0]['window_code']);
        try {
            $catalog->validateSelection($maker, $offering['service_offering_id'], $offering['service_offering_version_id'], $preWindowContext);
            $this->fail('Final validation must still require the selected service-specific Pickup window.');
        } catch (ApiException $exception) {
            $this->assertSame('PICKUP_WINDOW_REQUIRED', $exception->details['reason_code']);
        }
        try {
            $catalog->validateSelection($maker, $otherOffering['service_offering_id'], $otherOffering['service_offering_version_id'], [
                ...$preWindowContext,
                'selected_option_version_ids' => [],
                'pickup_service_date' => now('Asia/Tehran')->toDateString(),
                'pickup_window_code' => 'MORNING',
            ]);
            $this->fail('A window from another Service Offering must be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame('PICKUP_WINDOW_INVALID', $exception->details['reason_code']);
        }

        $selectionContext = [...$this->consignmentDraft($type, $method, $offering), 'selected_option_version_ids' => [$options['SIGNATURE']['service_option_version_id']]];
        $resolvedOfferings = $catalog->resolve($maker, $selectionContext);
        $this->assertContains('EXPRESS_GROUND', array_column($resolvedOfferings, 'offering_code'));
        $this->assertNotContains('OTHER_GROUND', array_column($resolvedOfferings, 'offering_code'));
        foreach ([
            'SERVICE_OPTION_REQUIRED' => [[], true],
            'SERVICE_OPTION_FORBIDDEN' => [[$options['SIGNATURE']['service_option_version_id'], $options['DANGEROUS']['service_option_version_id']], true],
            'SERVICE_OPTION_CONDITION_NOT_MET' => [[$options['SIGNATURE']['service_option_version_id'], $options['INSURANCE']['service_option_version_id']], false],
        ] as $reasonCode => [$selectedOptions, $insuranceEnabled]) {
            try {
                $catalog->validateSelection($maker, $offering['service_offering_id'], $offering['service_offering_version_id'], [...$selectionContext, 'selected_option_version_ids' => $selectedOptions, 'insurance_enabled' => $insuranceEnabled]);
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
        $insuranceChargeId = (string) DB::table('pricing_charge_types')->where('code', 'INSURANCE')->value('charge_type_id');
        $pickupChargeId = (string) DB::table('pricing_charge_types')->where('code', 'PICKUP_FEE')->value('charge_type_id');
        $taxChargeId = (string) DB::table('pricing_charge_types')->where('code', 'TAX')->value('charge_type_id');
        $tariff = $pricing->createTariff($maker, [
            'code' => 'STANDARD_SALES', 'title' => 'تعرفه فروش اکسپرس', 'purpose' => 'SALES', 'currency' => 'IRR', 'scope_type' => 'TENANT',
            'scope_value' => $tenant['hq_id'], 'priority' => 100, 'zone_set_version_id' => $zoneSet['zone_set_version_id'],
            'valid_from' => $validFrom, 'valid_to' => null, 'volumetric_divisor' => 5000,
            'weight_rounding_step_kg' => 0.5, 'rounding_mode' => 'STEP_UP',
            'rules' => [
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $baseChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'FIXED', 'basis' => 'SHIPMENT', 'fixed_amount' => 10000, 'priority' => 10],
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $insuranceChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'PERCENT', 'basis' => 'DECLARED_VALUE', 'percentage_bps' => 2, 'amount_rounding_mode' => 'CEIL', 'amount_rounding_step' => 10000, 'priority' => 20],
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'service_option_version_id' => $options['PACKAGING']['service_option_version_id'], 'charge_type_id' => $pickupChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'FIXED', 'basis' => 'SHIPMENT', 'fixed_amount' => 3000, 'priority' => 30],
                ['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => $taxChargeId, 'origin_zone_id' => $zoneId, 'destination_zone_id' => $zoneId, 'calculation_method' => 'PERCENT', 'basis' => 'SHIPMENT', 'percentage_bps' => 900, 'basis_charge_codes' => ['BASE_FREIGHT'], 'priority' => 100],
            ],
        ], (string) Str::uuid());
        $pricing->transition($maker, 'tariffs', $tariff['tariff_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'tariffs', $tariff['tariff_version_id'], 'publish', (string) Str::uuid());

        $draft = $this->consignmentDraft($type, $method, $offering);
        $draft['selected_option_version_ids'] = [$options['SIGNATURE']['service_option_version_id']];
        $quote = $this->app->make(ConsignmentPricingService::class)->calculate($maker, $nodeId, 'CREATE', $draft, null, null);
        $this->assertSame(20900, $quote['options'][0]['total_amount']);
        $this->assertCount(3, $quote['options'][0]['charge_lines']);
        $draft['selected_option_version_ids'][] = $options['PACKAGING']['service_option_version_id'];
        $quote = $this->app->make(ConsignmentPricingService::class)->calculate($maker, $nodeId, 'CREATE', $draft, null, null);
        $this->assertSame(23900, $quote['options'][0]['total_amount']);
        $this->assertCount(4, $quote['options'][0]['charge_lines']);
        $internalQuote = $pricing->quoteDetail($maker, $quote['options'][0]['internal_quote_id']);
        $this->assertSame('PER_PARCEL', $internalQuote['resolution_evidence']['weight']['weight_evidence']);
        $this->assertSame('CITY', $internalQuote['resolution_evidence']['origin']['member_type']);
        $this->assertSame(1.5, (float) $internalQuote['resolution_evidence']['weight']['billable_weight_kg']);
        $this->assertSame(1.5, $quote['options'][0]['billable_weight_kg']);
        $this->assertSame($schedule['commitment_schedule_version_id'], $internalQuote['resolution_evidence']['service']['commitment']['schedule_version_id']);

        $successor = $pricing->cloneDraft($maker, 'zone-sets', $zoneSet['pricing_zone_set_id'], (string) Str::uuid());
        $additionalCityId = (string) DB::table('cities')
            ->where('province_id', GeographyIds::province('8'))
            ->where('city_id', '!=', GeographyIds::city('10866'))
            ->where('is_active', true)
            ->orderBy('legacy_city_code')
            ->value('city_id');
        $successorZones = collect($successor['zones'])->map(function (array $zone) use ($additionalCityId): array {
            $members = collect($zone['members'])->map(fn (array $member): array => [
                'member_type' => $member['member_type'],
                'reference_value' => $member['reference_value'],
                'city_id' => $member['city_id'],
                'province_id' => $member['province_id'],
                'range_end' => $member['range_end'],
            ])->all();
            if ($zone['code'] === 'TEHRAN') {
                $members[] = ['member_type' => 'CITY', 'city_id' => $additionalCityId];
            }

            return ['code' => $zone['code'], 'title' => $zone['title'], 'remote_area' => $zone['remote_area'], 'members' => $members];
        })->all();
        $successor = $pricing->updateZoneVersion($maker, $successor['zone_set_version_id'], [
            'expected_version' => 1,
            'valid_from' => $validFrom,
            'valid_to' => null,
            'zones' => $successorZones,
        ]);
        $pricing->transition($maker, 'zone-sets', $zoneSet['zone_set_version_id'], 'supersede', (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $successor['zone_set_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $successor['zone_set_version_id'], 'publish', (string) Str::uuid());

        $successorDraft = $draft;
        $successorDraft['sender']['city_id'] = $additionalCityId;
        $successorDraft['receiver']['city_id'] = $additionalCityId;
        $successorQuote = $pricing->calculateQuote($maker, $successorDraft, 'published-zone-successor');
        $this->assertSame(23900, $successorQuote['total_amount']);
        $this->assertSame($tariff['tariff_version_id'], $successorQuote['tariff_version_id']);
        $this->assertSame($successor['zone_set_version_id'], $successorQuote['zone_set_version_id']);
        $this->assertSame($zoneSet['zone_set_version_id'], $successorQuote['resolution_evidence']['zone_set']['configured_version_id']);
        $this->assertSame($successor['zone_set_version_id'], $successorQuote['resolution_evidence']['zone_set']['resolved_version_id']);
        $this->assertTrue($pricing->validateTariff($maker, $tariff['tariff_version_id'])['valid'], 'Superseding the configured zone version must not invalidate a tariff with an effective group successor.');

        $created = $this->app->make(ConsignmentService::class)->create($maker, $nodeId, [...$draft, 'accepted_quote' => [
            'quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id'],
        ]], (string) Str::uuid());

        $this->assertSame('LOCKED', $created['commercial_pricing_state']);
        $this->assertSame($offering['service_offering_version_id'], $created['service_offering_version_id']);
        $this->assertSame($schedule['commitment_schedule_version_id'], $created['commitment_schedule_version_id']);
        $this->assertSame('Express Ground', $created['service_offering_title']);
        $editDraft = [...$draft, 'pickup_commitment_at' => $created['pickup_commitment_at'], 'delivery_commitment_at' => $created['delivery_commitment_at']];
        $editDraft['sender']['contact_name'] = 'Edited sender';
        $editDraft['payer'] = 'RECEIVER';
        $editDraft['payment_method'] = 'CREDIT';
        $editDraft['parcels'][0]['content_description'] = 'Edited contents';
        $editQuote = $this->app->make(ConsignmentPricingService::class)->calculate($maker, $nodeId, 'EDIT', $editDraft, $created['consignment_id'], 1);
        $edited = $this->app->make(ConsignmentService::class)->edit($maker, $nodeId, $created['consignment_id'], [
            'expected_version' => 1, 'change_reason' => 'Edit fields with resolved SLA', 'sender' => $editDraft['sender'], 'payer' => 'RECEIVER', 'payment_method' => 'CREDIT',
            'parcels' => array_map(fn ($parcel, $index) => [...array_fill_keys(['content_description', 'weight_kg', 'width_cm', 'length_cm', 'height_cm'], null), ...$parcel, 'parcel_id' => $created['parcels'][$index]['parcel_id']], $editDraft['parcels'], array_keys($editDraft['parcels'])),
            'accepted_quote' => ['quote_id' => $editQuote['quote_id'], 'quote_version' => 1, 'option_id' => $editQuote['options'][0]['option_id']],
        ], (string) Str::uuid());
        $this->assertSame(2, $edited['version']);
        $this->assertSame('Edited contents', $edited['parcels'][0]['content_description']);
        $this->assertSame($created['parcels'][0]['parcel_id'], $edited['parcels'][0]['parcel_id']);
        $this->assertSame($created['accepted_pricing_versions'][0], collect($edited['accepted_pricing_versions'])->firstWhere('pricing_version_id', $created['accepted_pricing_versions'][0]['pricing_version_id']));
        $this->assertContains('latitude', $edited['non_pricing_contact_fields']);
        $locationEdit = $this->app->make(ConsignmentService::class)->edit($maker, $nodeId, $created['consignment_id'], [
            'expected_version' => 2, 'change_reason' => 'Correct location without changing city tariff',
            'sender' => ['latitude' => 35.7, 'longitude' => 51.4],
        ], (string) Str::uuid());
        $this->assertSame('LOCKED', $locationEdit['commercial_pricing_state']);
        $this->assertSame($edited['accepted_pricing_versions'], $locationEdit['accepted_pricing_versions']);
        $this->assertSame(35.7, $locationEdit['sender']['latitude']);
        $this->assertSame('Ground', $created['shipping_method_title']);
        $this->assertSame('MORNING', $created['pickup_window_code']);
        $this->assertNotNull($created['delivery_commitment_at']);
        $this->assertDatabaseHas('pricing_snapshots', ['object_id' => $created['consignment_id'], 'total_amount' => 23900]);
        $this->assertDatabaseHas('pricing_snapshots', ['object_id' => $created['consignment_id'], 'quote_id' => $internalQuote['quote_id']]);
        $this->assertDatabaseHas('pricing_quotes', ['quote_id' => $internalQuote['quote_id'], 'tariff_version_id' => $tariff['tariff_version_id'], 'zone_set_version_id' => $zoneSet['zone_set_version_id']]);
        $this->assertDatabaseCount('pricing_charge_lines', 8);
        $this->assertDatabaseHas('consignment_pricing_versions', ['consignment_id' => $created['consignment_id'], 'provider_code' => 'INTERNAL', 'total_amount' => 23900]);

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

        $this->assertSame('تعرفه فروش اکسپرس', $tariff['title']);
        $this->assertSame('تعرفه فروش اکسپرس', $pricing->listTariffs($maker, ['search' => 'اکسپرس'])->items()[0]->title);
        $noWindowOffering = $catalog->createIdentity($maker, 'offerings', [
            'code' => 'WITHOUT_WINDOWS', 'labels' => ['fa' => 'حمل بدون بازه'], 'description' => null,
            'service_type_version_id' => $type['service_type_version_id'],
            'shipping_method_version_id' => $method['shipping_method_version_id'],
            'sla_policy' => [], 'availability_summary' => [], 'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [],
            'commitment_binding' => ['commitment_schedule_version_id' => $schedule['commitment_schedule_version_id'], 'pickup_mode' => 'NONE', 'delivery_mode' => 'NONE'],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'enabled' => true]],
            'valid_from' => $validFrom, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $noWindowOffering['service_offering_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $noWindowOffering['service_offering_version_id'], 'publish', (string) Str::uuid());
        $withoutWindows = $scheduleService->resolveForOffering($noWindowOffering['service_offering_version_id'], ['schedule_node_ids'=>[$nodeId]], true);
        $this->assertTrue($withoutWindows['eligible']);
        $this->assertSame('NONE', $withoutWindows['pickup']['mode']);
        $this->assertSame('NONE', $withoutWindows['delivery']['mode']);
        config()->set('chabok.pricing.quote_ttl_seconds', 300);
        $noWindowTariff = $pricing->createTariff($maker, [
            'code' => 'NO_WINDOW_SALES', 'title' => 'تعرفه بدون بازه', 'purpose' => 'SALES', 'currency' => 'IRR',
            'scope_type' => 'TENANT', 'zone_set_version_id' => $successorQuote['zone_set_version_id'],
            'valid_from' => $validFrom, 'valid_to' => null,
            'rules' => [
                ['service_offering_version_id' => $noWindowOffering['service_offering_version_id'], 'charge_type_id' => $baseChargeId, 'calculation_method' => 'FIXED', 'basis' => 'SHIPMENT', 'fixed_amount' => 10000, 'priority' => 10],
                ['service_offering_version_id' => $noWindowOffering['service_offering_version_id'], 'charge_type_id' => $insuranceChargeId, 'calculation_method' => 'PERCENT', 'basis' => 'DECLARED_VALUE', 'percentage_bps' => 2, 'amount_rounding_mode' => 'CEIL', 'amount_rounding_step' => 10000, 'priority' => 20],
            ],
        ], (string) Str::uuid());
        $pricing->transition($maker, 'tariffs', $noWindowTariff['tariff_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'tariffs', $noWindowTariff['tariff_version_id'], 'publish', (string) Str::uuid());
        $noWindowDraft = [...$this->consignmentDraft($type, $method, $noWindowOffering), 'pickup_service_date' => null, 'pickup_window_code' => null, 'delivery_window_code' => null, 'pickup_commitment_at' => null, 'delivery_commitment_at' => null, 'selected_option_version_ids' => []];
        $noWindowQuote = $this->app->make(ConsignmentPricingService::class)->calculate($maker, $nodeId, 'CREATE', $noWindowDraft, null, null);
        $noWindowCreated = $this->app->make(ConsignmentService::class)->create($maker, $nodeId, [
            ...$noWindowDraft,
            'accepted_quote' => ['quote_id' => $noWindowQuote['quote_id'], 'quote_version' => $noWindowQuote['quote_version'], 'option_id' => $noWindowQuote['options'][0]['option_id']],
        ], (string) Str::uuid());
        $this->assertNull($noWindowCreated['pickup_window_code']);
        $this->assertNull($noWindowCreated['pickup_commitment_at']);
        $this->assertNull($noWindowCreated['delivery_commitment_at']);
        $this->assertSame('LOCKED', $noWindowCreated['commercial_pricing_state']);

        // Direct edits must flow through existing tariff and schedule bindings only for new issuance.
        $records = $this->app->make(CatalogRecordService::class);
        $oldConsignment = (array) DB::table('consignments')->where('consignment_id', $created['consignment_id'])->first();
        $oldQuote = (array) DB::table('pricing_quotes')->where('quote_id', $internalQuote['quote_id'])->first();
        $staleQuote = $this->app->make(ConsignmentPricingService::class)->calculate($maker, $nodeId, 'CREATE', $draft, null, null);
        $oldBinding = (array) DB::table('service_offering_commitment_bindings')->where('service_offering_version_id', $offering['service_offering_version_id'])->first();
        $scheduleInput = $records->detail($maker, 'commitment-schedules', $schedule['commitment_schedule_id']);
        $scheduleInput['expected_version'] = $scheduleInput['lock_version'];
        $scheduleInput['windows'][0]['end_time'] = '15:00';
        $scheduleInput['title'] = 'برنامهٔ جاری جدید';
        $changedSchedule = $records->save($maker, 'commitment-schedules', $schedule['commitment_schedule_id'], $scheduleInput, (string) Str::uuid());
        $this->assertSame('ACTIVE', $changedSchedule['status']);
        $this->assertNotSame($schedule['commitment_schedule_version_id'], $changedSchedule['commitment_schedule_version_id']);
        $this->assertSame($oldBinding, (array) DB::table('service_offering_commitment_bindings')->where('service_offering_version_id', $offering['service_offering_version_id'])->first());
        $serviceInput = $records->detail($maker, 'offerings', $offering['service_offering_id']);
        $serviceInput['expected_version'] = $serviceInput['lock_version'];
        $serviceInput['labels']['fa'] = 'سرویس با نام جدید';
        $changedOffering = $records->save($maker, 'offerings', $offering['service_offering_id'], $serviceInput, (string) Str::uuid());
        foreach (['shipping-methods' => $method['shipping_method_id'], 'service-types' => $type['service_type_id'], 'options' => $options['SIGNATURE']['service_option_id']] as $resource => $identity) {
            $change = $records->detail($maker, $resource, $identity);
            $change['expected_version'] = $change['lock_version'];
            $change['labels']['fa'] = 'عنوان جاری ' . $resource;
            $records->save($maker, $resource, $identity, $change, (string) Str::uuid());
        }
        try {
            $this->app->make(ConsignmentService::class)->create($maker, $nodeId, [...$draft, 'accepted_quote' => ['quote_id' => $staleQuote['quote_id'], 'quote_version' => $staleQuote['quote_version'], 'option_id' => $staleQuote['options'][0]['option_id']]], (string) Str::uuid());
            $this->fail('Unissued quotes must not retain catalog settings after an edit.');
        } catch (ApiException $error) { $this->assertSame('CATALOG_CHANGED', $error->details['reason_code']); }
        $fresh = $this->app->make(ConsignmentPricingService::class)->calculate($maker, $nodeId, 'CREATE', $draft, null, null);
        $newConsignment = $this->app->make(ConsignmentService::class)->create($maker, $nodeId, [...$draft, 'accepted_quote' => ['quote_id' => $fresh['quote_id'], 'quote_version' => $fresh['quote_version'], 'option_id' => $fresh['options'][0]['option_id']]], (string) Str::uuid());
        $this->assertSame($changedOffering['service_offering_version_id'], $newConsignment['service_offering_version_id']);
        $this->assertSame($changedSchedule['commitment_schedule_version_id'], $newConsignment['commitment_schedule_version_id']);
        $this->assertSame('سرویس با نام جدید', $newConsignment['service_offering_title']);
        $this->assertSame('عنوان جاری shipping-methods', $newConsignment['shipping_method_title']);
        $this->assertSame('عنوان جاری service-types', $newConsignment['service_type_title']);
        $this->assertContains('عنوان جاری options', array_map(fn ($option) => $option['labels']['fa'] ?? '', $newConsignment['catalog_snapshot']['selected_services']));
        $this->assertNotSame($created['delivery_commitment_at'], $newConsignment['delivery_commitment_at']);
        $this->assertSame($oldConsignment, (array) DB::table('consignments')->where('consignment_id', $created['consignment_id'])->first());
        $this->assertSame($oldQuote, (array) DB::table('pricing_quotes')->where('quote_id', $internalQuote['quote_id'])->first());
        $replayed = $records->save($maker, 'offerings', $offering['service_offering_id'], $serviceInput, (string) Str::uuid());
        $this->assertSame($changedOffering['service_offering_version_id'], $replayed['service_offering_version_id']);
        $this->assertSame($changedOffering['lock_version'], $replayed['lock_version']);
        $policyInput=$records->detail($maker,'commitment-schedules',$schedule['commitment_schedule_id']);
        $policyInput['expected_version']=$policyInput['lock_version'];
        $policyInput['commitment_policy']=\Modules\ServiceCatalog\Application\SchedulePolicy::fromBinding(['pickup_mode'=>'NONE','delivery_mode'=>'COMPUTED','duration_value'=>24,'duration_unit'=>'HOUR','duration_anchor'=>'CONSIGNMENT_CREATED']);
        $policyInput['commitment_policy']['zone_set_id']=$zoneSet['pricing_zone_set_id'];
        $policyInput['commitment_policy']['destination_rules']=[['id'=>(string)Str::uuid(),'destination_zone_code'=>'TEHRAN','origin_zone_code'=>null,'policy'=>[...$policyInput['commitment_policy']['delivery'],'duration_value'=>12]]];
        $policySchedule=$records->save($maker,'commitment-schedules',$schedule['commitment_schedule_id'],$policyInput,(string)Str::uuid());
        $policyQuote=$this->app->make(ConsignmentPricingService::class)->calculate($maker,$nodeId,'CREATE',$draft,null,null);
        $policyConsignment=$this->app->make(ConsignmentService::class)->create($maker,$nodeId,[...$draft,'accepted_quote'=>['quote_id'=>$policyQuote['quote_id'],'quote_version'=>$policyQuote['quote_version'],'option_id'=>$policyQuote['options'][0]['option_id']]],(string)Str::uuid());
        $this->assertSame($policySchedule['commitment_schedule_version_id'],$policyConsignment['commitment_schedule_version_id']);
        $this->assertSame($policyInput['commitment_policy']['destination_rules'][0]['id'],$policyConsignment['commitment_snapshot']['selected_rule_id']);
        $this->assertSame($policyConsignment['commitment_snapshot']['delivery']['computed_at'],$policyConsignment['delivery_commitment_at']);
        $this->assertSame($oldConsignment,(array)DB::table('consignments')->where('consignment_id',$created['consignment_id'])->first());
        $inactive = $records->setActive($maker, 'offerings', $offering['service_offering_id'], false, $changedOffering['lock_version'], (string) Str::uuid());
        $this->assertNotContains($offering['service_offering_id'], array_column($catalog->resolve($maker, $draft), 'service_offering_id'));
        $this->assertSame($oldConsignment, (array) DB::table('consignments')->where('consignment_id', $created['consignment_id'])->first());
        $this->assertSame('ACTIVE', $records->setActive($maker, 'offerings', $offering['service_offering_id'], true, $inactive['lock_version'], (string) Str::uuid())['status']);

    }

    public function test_pricing_polygon_configuration_rejects_collisions_and_preserves_legacy_members(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant, $maker] = $this->administratorContext('POLYGONS');
        $pricing = $this->app->make(PricingService::class);
        $square = fn (float $x) => ['type' => 'Polygon', 'coordinates' => [[[$x, 35], [$x + 1, 35], [$x + 1, 36], [$x, 36], [$x, 35]]]];
        $zones = [
            ['code' => 'AA', 'title' => 'الف', 'remote_area' => true, 'members' => [['member_type' => 'POLYGON', 'geometry' => $square(50)], ['member_type' => 'EXPLICIT_OVERRIDE', 'reference_value' => 'VIP'], ['member_type' => 'POSTAL_RANGE', 'reference_value' => '۰۰۰۰۰۰۰۰۰۱', 'range_end' => '٠٠٠٠٠٠٠٠٠٩']]],
            ['code' => 'BB', 'title' => 'ب', 'members' => [['member_type' => 'POLYGON', 'geometry' => $square(53)]]],
        ];
        $token = $this->login('polygons-maker')['token'];
        $response = $this->withToken($token)->postJson('/api/v1/admin/pricing/zone-sets', ['code' => 'POLYGON_SET', 'title' => 'محدوده‌ها', 'purpose' => 'SALES', 'valid_from' => now()->subMinute()->toISOString(), 'zones' => $zones])->assertCreated();
        $saved = $response->json('data'); $id = $saved['zone_set_version_id'];
        $aa = array_search('AA', array_column($saved['zones'], 'code')); $bb = array_search('BB', array_column($saved['zones'], 'code'));
        $polygonIndex = array_search('POLYGON', array_column($saved['zones'][$aa]['members'], 'member_type'));
        $postalIndex = array_search('POSTAL_RANGE', array_column($saved['zones'][$aa]['members'], 'member_type'));
        $overrideIndex = array_search('EXPLICIT_OVERRIDE', array_column($saved['zones'][$aa]['members'], 'member_type'));
        $this->assertEquals($square(50), $saved['zones'][$aa]['members'][$polygonIndex]['geometry']);
        $this->assertSame('0000000001', $saved['zones'][$aa]['members'][$postalIndex]['reference_value']);
        $this->assertTrue($pricing->validateZoneSet($maker, $id)['valid']);
        $resolve = new \ReflectionMethod($pricing, 'resolveZone');
        [$zone, $evidence] = $resolve->invoke($pricing, $id, ['latitude' => 35, 'longitude' => 50], 'sender');
        $this->assertSame('AA', $zone['code']); $this->assertSame(250, $evidence['precedence']);
        [, $postalEvidence] = $resolve->invoke($pricing, $id, ['latitude' => 35.5, 'longitude' => 50.5, 'postal_code' => '0000000005'], 'sender');
        $this->assertSame('POSTAL_RANGE', $postalEvidence['member_type']);
        [, $overrideEvidence] = $resolve->invoke($pricing, $id, ['latitude' => 35.5, 'longitude' => 50.5, 'postal_code' => '0000000005', 'zone_override' => 'VIP'], 'sender');
        $this->assertSame('EXPLICIT_OVERRIDE', $overrideEvidence['member_type']);
        foreach ([50.5, 51.0] as $overlap) {
            $candidate = $saved['zones']; $candidate[$bb]['members'][0]['geometry'] = $square($overlap);
            try { $pricing->updateZoneVersion($maker, $id, ['expected_version' => 1, 'zones' => $candidate]); $this->fail('Overlap or shared boundary must fail before mutation.'); }
            catch (ApiException $error) { $this->assertSame(ApiErrorCode::PricingZoneAmbiguous, $error->errorCode); }
            $this->assertSame($saved, $pricing->zoneVersion($maker, $id));
        }
        foreach ([['type' => 'Polygon', 'coordinates' => [[[50, 35], [51, 36], [50, 36], [51, 35], [50, 35]]]], $square(181)] as $invalid) {
            $candidate = $saved['zones']; $candidate[$aa]['members'][$polygonIndex]['geometry'] = $invalid;
            try { $pricing->updateZoneVersion($maker, $id, ['expected_version' => 1, 'zones' => $candidate]); $this->fail('Invalid geometry must fail.'); }
            catch (ApiException $error) { $this->assertSame(ApiErrorCode::ValidationError, $error->errorCode); }
        }
        $noRole = $this->user($tenant['hq_id'], 'polygon-no-role');
        try { $pricing->updateZoneVersion(new AuthenticatedPrincipal($noRole['user_id'], (string) Str::uuid(), $tenant['hq_id'], false), $id, ['expected_version' => 1, 'zones' => $saved['zones']]); $this->fail('Permission required.'); }
        catch (ApiException $error) { $this->assertSame(ApiErrorCode::PermissionDenied, $error->errorCode); }
        [, $foreign] = $this->administratorContext('POLYGON-FOREIGN');
        try { $pricing->updateZoneVersion($foreign, $id, ['expected_version' => 1, 'zones' => $saved['zones']]); $this->fail('Foreign tenant must fail.'); }
        catch (ApiException $error) { $this->assertContains($error->errorCode, [ApiErrorCode::TenantAccessDenied, ApiErrorCode::ResourceNotFound]); }
        // Simulate an existing historical-length draft without normalizing it.
        DB::table('pricing_zone_members')->where('zone_member_id', $saved['zones'][$aa]['members'][$postalIndex]['zone_member_id'])->update(['reference_value' => '001', 'range_end' => '009']);
        $legacy = $pricing->zoneVersion($maker, $id); $legacy['zones'][$aa]['title'] = 'عنوان ویرایش‌شده';
        $updated = $pricing->updateZoneVersion($maker, $id, ['expected_version' => 1, 'zones' => $legacy['zones']]);
        $aa = array_search('AA', array_column($updated['zones'], 'code'));
        $postalIndex = array_search('POSTAL_RANGE', array_column($updated['zones'][$aa]['members'], 'member_type'));
        $overrideIndex = array_search('EXPLICIT_OVERRIDE', array_column($updated['zones'][$aa]['members'], 'member_type'));
        $this->assertSame('001', $updated['zones'][$aa]['members'][$postalIndex]['reference_value']);
        $this->assertTrue((bool) $updated['zones'][$aa]['remote_area']);
        $this->assertSame('VIP', $updated['zones'][$aa]['members'][$overrideIndex]['reference_value']);
        $candidate = $updated['zones']; $candidate[$aa]['members'][$postalIndex]['range_end'] = '008';
        try { $pricing->updateZoneVersion($maker, $id, ['expected_version' => 2, 'zones' => $candidate]); $this->fail('Changed legacy range requires ten digits.'); }
        catch (ApiException $error) { $this->assertSame(ApiErrorCode::ValidationError, $error->errorCode); }
    }

    public function test_pricing_polygon_holes_multipolygons_and_invalid_topology(): void
    {
        $geometry = $this->app->make(\Modules\Geography\Application\PolygonGeometry::class);
        $shell = [[50, 35], [54, 35], [54, 39], [50, 39], [50, 35]];
        $hole = [[51, 36], [52, 36], [52, 37], [51, 37], [51, 36]];
        $valid = $geometry->normalize(['type' => 'Polygon', 'coordinates' => [$shell, $hole]]);
        $this->assertTrue($geometry->contains($valid, 35, 50));
        $this->assertFalse($geometry->contains($valid, 36.5, 51.5));
        $this->assertTrue($geometry->contains($valid, 36, 51));
        $multi = $geometry->normalize(['type' => 'MultiPolygon', 'coordinates' => [[$shell, $hole], [[[60, 30], [61, 30], [61, 31], [60, 31], [60, 30]]]]]);
        $this->assertTrue($geometry->contains($multi, 30.5, 60.5));
        $this->assertFalse($geometry->contains($multi, 34, 56));
        foreach ([['type' => 'Polygon', 'coordinates' => [$hole, $shell]], ['type' => 'Polygon', 'coordinates' => [[[50, 35], [51, 35], [52, 35], [50, 35]]]]] as $invalid) {
            try { $geometry->normalize($invalid); $this->fail('Invalid holes or zero area must fail.'); }
            catch (ApiException $error) { $this->assertSame(ApiErrorCode::ValidationError, $error->errorCode); }
        }
    }

    public function test_reusable_service_tariffs_follow_publication_and_workbook_roundtrip(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $this->app->make(PricingChargeTypeSeeder::class)->run();
        [$tenant, $maker, $checker] = $this->administratorContext('SERVICES');
        $catalog = $this->app->make(ServiceCatalogService::class);
        $pricing = $this->app->make(PricingService::class);
        $type = $catalog->createIdentity($maker, 'service-types', ['code' => 'V2_TYPE', 'labels' => ['fa' => 'سرویس آزمون'], 'definition' => [], 'valid_from' => null, 'valid_to' => null], (string) Str::uuid());
        $catalog->transition($checker, 'service-types', $type['service_type_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'service-types', $type['service_type_version_id'], 'publish', (string) Str::uuid());
        $method = $catalog->createIdentity($maker, 'shipping-methods', ['code' => 'V2_GROUND', 'labels' => ['fa' => 'زمینی'], 'definition' => [], 'valid_from' => null, 'valid_to' => null], (string) Str::uuid());
        $catalog->transition($checker, 'shipping-methods', $method['shipping_method_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'shipping-methods', $method['shipping_method_version_id'], 'publish', (string) Str::uuid());
        $offering = $catalog->createIdentity($maker, 'offerings', [
            'code' => 'V2_SERVICE', 'labels' => ['fa' => 'سرویس مستقل'], 'service_type_version_id' => $type['service_type_version_id'], 'shipping_method_version_id' => $method['shipping_method_version_id'],
            'sla_policy' => ['commitment_type' => 'DURATION', 'duration_value' => 24, 'duration_unit' => 'HOUR'], 'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'enabled' => true]], 'valid_from' => null, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($checker, 'offerings', $offering['service_offering_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $offering['service_offering_version_id'], 'publish', (string) Str::uuid());
        $cities = DB::table('cities')->where('is_active', true)->orderBy('city_id')->limit(2)->pluck('city_id')->all();
        $zoneSet = $pricing->createZoneSet($maker, ['code' => 'V2_ZONES', 'purpose' => 'SALES', 'title' => 'مناطق رتبه‌ای', 'valid_from' => now()->subMinute()->toISOString(), 'zones' => [
            ['code' => 'AA', 'title' => 'منطقه الف', 'rank' => 2, 'members' => [['member_type' => 'CITY', 'city_id' => $cities[0]], ['member_type' => 'POLYGON', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[50, 35], [51, 35], [51, 36], [50, 36], [50, 35]]]]]]],
            ['code' => 'BB', 'title' => 'منطقه ب', 'rank' => 9, 'members' => [['member_type' => 'CITY', 'city_id' => $cities[1]]]],
        ]], (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $zoneSet['zone_set_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $zoneSet['zone_set_version_id'], 'publish', (string) Str::uuid());
        $zones = collect($zoneSet['zones'])->keyBy('code');
        $matrix = ['id' => (string) Str::uuid(), 'service_offering_version_id' => $offering['service_offering_version_id'], 'service_option_version_id' => null, 'origin_zone_id' => null, 'zone_ids' => [$zones['AA']['pricing_zone_id'], $zones['BB']['pricing_zone_id']], 'bands' => [
            ['id' => (string) Str::uuid(), 'from' => 0.5, 'to' => 2, 'cells' => [
                ['id' => (string) Str::uuid(), 'zone_id' => $zones['AA']['pricing_zone_id'], 'state' => 'RATE', 'amount' => 1000],
                ['id' => (string) Str::uuid(), 'zone_id' => $zones['BB']['pricing_zone_id'], 'state' => 'RATE', 'amount' => 5000],
            ]],
        ]];

        $global = \Modules\Pricing\Application\TariffMatrixCompiler::GLOBAL_COLUMN;
        $cell = static fn(int $amount)=>['id'=>(string)Str::uuid(),'zone_id'=>$global,'state'=>'RATE','amount'=>$amount];
        $serviceMatrix=['id'=>(string)Str::uuid(),'service_offering_version_id'=>null,'service_option_version_id'=>null,'origin_zone_id'=>null,'zone_ids'=>[$global],
            'bands'=>[['id'=>(string)Str::uuid(),'from'=>0,'to'=>20000000,'cells'=>[$cell(200000)]]],
            'linear_bands'=>[['id'=>(string)Str::uuid(),'from'=>20000000,'to'=>null,'step_kg'=>10000000,'cells'=>[$cell(50000)]]]];
        $serviceInput=['title'=>'بیمهٔ ماتریسی','tariff_kind'=>'SERVICE','service_charge_type_id'=>DB::table('pricing_charge_types')->where('code','INSURANCE')->value('charge_type_id'),'matrix_basis'=>'DECLARED_VALUE','zone_set_version_id'=>null,'purpose'=>'SALES','currency'=>'IRR','valid_from'=>now()->subMinute()->toISOString(),'rules'=>[],'freight_matrices'=>[$serviceMatrix]];
        $insurance=$pricing->createTariff($maker,$serviceInput,(string)Str::uuid());
        $this->assertTrue($pricing->validateTariff($maker,$insurance['tariff_version_id'])['valid']);
        foreach(['approve','publish'] as $action) $pricing->transition($maker,'tariffs',$insurance['tariff_version_id'],$action,(string)Str::uuid());
        $freightInput=['title'=>'حمل با بیمه متصل','purpose'=>'SALES','currency'=>'IRR','is_default'=>true,'zone_set_version_id'=>$zoneSet['zone_set_version_id'],'zone_policy'=>'HIGHER_ZONE_RANK','valid_from'=>now()->subMinute()->toISOString(),'freight_matrices'=>[$matrix],'rules'=>[],'service_tariff_family_ids'=>[$insurance['tariff_family_id']]];
        $legacy=$pricing->createTariff($maker,[...$freightInput,'is_default'=>false,'priority'=>1],(string)Str::uuid());
        foreach(['approve','publish'] as $action) $pricing->transition($maker,'tariffs',$legacy['tariff_version_id'],$action,(string)Str::uuid());
        $freight=$pricing->createTariff($maker,$freightInput,(string)Str::uuid());
        foreach(['approve','publish'] as $action) $pricing->transition($maker,'tariffs',$freight['tariff_version_id'],$action,(string)Str::uuid());
        $input=['service_offering_id'=>$offering['service_offering_id'],'service_offering_version_id'=>$offering['service_offering_version_id'],'sender'=>['city_id'=>$cities[0],'latitude'=>35.5,'longitude'=>50.5],'receiver'=>['city_id'=>$cities[1],'latitude'=>38,'longitude'=>53],'parcels'=>[['weight_kg'=>1]],'declared_value_amount'=>20000001,'insurance_enabled'=>true,'selected_option_version_ids'=>[]];
        $quote=$pricing->calculateQuote($maker,$input,(string)Str::uuid());
        $this->assertSame(255000,$quote['total_amount']);
        $this->assertSame($freight['tariff_version_id'],$quote['tariff_version_id']);
        $this->assertSame($insurance['tariff_version_id'],$quote['resolution_evidence']['service_tariffs'][0]['tariff_version_id']);
        $successor=$pricing->cloneDraft($maker,'tariffs',$insurance['tariff_family_id'],(string)Str::uuid());
        $serviceMatrix['linear_bands'][0]['cells'][0]['amount']=80000;
        $successor=$pricing->updateTariffVersion($maker,$successor['tariff_version_id'],[...$serviceInput,'freight_matrices'=>[$serviceMatrix],'expected_version'=>$successor['lock_version']]);
        foreach(['approve','publish'] as $action) $pricing->transition($maker,'tariffs',$successor['tariff_version_id'],$action,(string)Str::uuid());
        $new=$pricing->calculateQuote($maker,$input,(string)Str::uuid());
        $this->assertSame(285000,$new['total_amount']);
        $this->assertSame($successor['tariff_version_id'],$new['resolution_evidence']['service_tariffs'][0]['tariff_version_id']);
        $this->assertSame($quote,$pricing->quoteDetail($maker,$quote['quote_id']));
        $zoneSuccessor=$pricing->cloneDraft($maker,'zone-sets',$zoneSet['pricing_zone_set_id'],(string)Str::uuid());
        $zoneSuccessor=$pricing->updateZoneVersion($maker,$zoneSuccessor['zone_set_version_id'],[...$zoneSuccessor,'valid_from'=>now()->subSecond()->toISOString(),'expected_version'=>$zoneSuccessor['lock_version']]);
        foreach(['approve','publish'] as $action) $pricing->transition($maker,'zone-sets',$zoneSuccessor['zone_set_version_id'],$action,(string)Str::uuid());
        $next=$pricing->calculateQuote($maker,$input,(string)Str::uuid());
        $this->assertSame($zoneSuccessor['zone_set_version_id'],$next['zone_set_version_id']);
        $this->assertSame(285000,$next['total_amount']);
        $conflict=$pricing->createTariff($maker,$freightInput,(string)Str::uuid());
        $this->assertContains('PRICING_DEFAULT_CONFLICT',array_column($pricing->validateTariff($maker,$conflict['tariff_version_id'])['errors'],'code'));
        [, $foreign] = $this->administratorContext('SERVICE-FOREIGN');
        $this->assertSame([], $pricing->serviceTariffReferences($foreign));
        try { $pricing->createTariff($maker,[...$freightInput,'service_tariff_family_ids'=>[$insurance['tariff_family_id'],$insurance['tariff_family_id']]],(string)Str::uuid()); $this->fail('Duplicate attachment accepted'); } catch(ApiException $e) { $this->assertSame(422,$e->httpStatus); }
        $workbook=$pricing->matrixWorkbook($maker,['zone_titles'=>['الف','ب']],true);
        $parsed=$pricing->matrixWorkbook($maker,['content_base64'=>$workbook['content_base64'],'matrix'=>$matrix],false);
        $this->assertSame(1,$parsed['band_count']); $this->assertSame(3,$parsed['linear_band_count']);
        $this->assertSame(0.5,$parsed['matrix']['linear_bands'][1]['step_kg']);
        $noRole=$this->user($tenant['hq_id'],'workbook-no-role');
        try { $pricing->matrixWorkbook(new AuthenticatedPrincipal($noRole['user_id'],(string)Str::uuid(),$tenant['hq_id'],false),['zone_titles'=>['الف']],true); $this->fail('Permission required'); } catch(ApiException $e) { $this->assertSame(ApiErrorCode::PermissionDenied,$e->errorCode); }
        try { $pricing->createTariff($foreign,[...$serviceInput,'service_tariff_family_ids'=>[$insurance['tariff_family_id']]],(string)Str::uuid()); $this->fail('Foreign attachment accepted'); } catch(ApiException $e) { $this->assertSame(422,$e->httpStatus); }

    }

    public function test_v2_ranked_matrix_draft_simulation_and_publication_share_the_engine(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $this->app->make(PricingChargeTypeSeeder::class)->run();
        [$tenant, $maker, $checker] = $this->administratorContext('V2');
        $catalog = $this->app->make(ServiceCatalogService::class);
        $pricing = $this->app->make(PricingService::class);
        $type = $catalog->createIdentity($maker, 'service-types', ['code' => 'V2_TYPE', 'labels' => ['fa' => 'سرویس آزمون'], 'definition' => [], 'valid_from' => null, 'valid_to' => null], (string) Str::uuid());
        $catalog->transition($checker, 'service-types', $type['service_type_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'service-types', $type['service_type_version_id'], 'publish', (string) Str::uuid());
        $method = $catalog->createIdentity($maker, 'shipping-methods', ['code' => 'V2_GROUND', 'labels' => ['fa' => 'زمینی'], 'definition' => [], 'valid_from' => null, 'valid_to' => null], (string) Str::uuid());
        $catalog->transition($checker, 'shipping-methods', $method['shipping_method_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'shipping-methods', $method['shipping_method_version_id'], 'publish', (string) Str::uuid());
        $offering = $catalog->createIdentity($maker, 'offerings', [
            'code' => 'V2_SERVICE', 'labels' => ['fa' => 'سرویس مستقل'], 'service_type_version_id' => $type['service_type_version_id'], 'shipping_method_version_id' => $method['shipping_method_version_id'],
            'sla_policy' => ['commitment_type' => 'DURATION', 'duration_value' => 24, 'duration_unit' => 'HOUR'], 'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenant['hq_id'], 'enabled' => true]], 'valid_from' => null, 'valid_to' => null,
        ], (string) Str::uuid());
        $catalog->transition($checker, 'offerings', $offering['service_offering_version_id'], 'approve', (string) Str::uuid());
        $catalog->transition($maker, 'offerings', $offering['service_offering_version_id'], 'publish', (string) Str::uuid());
        $cities = DB::table('cities')->where('is_active', true)->orderBy('city_id')->limit(2)->pluck('city_id')->all();
        $zoneSet = $pricing->createZoneSet($maker, ['code' => 'V2_ZONES', 'purpose' => 'SALES', 'title' => 'مناطق رتبه‌ای', 'valid_from' => now()->subMinute()->toISOString(), 'zones' => [
            ['code' => 'AA', 'title' => 'منطقه الف', 'rank' => 2, 'members' => [['member_type' => 'CITY', 'city_id' => $cities[0]], ['member_type' => 'POLYGON', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[50, 35], [51, 35], [51, 36], [50, 36], [50, 35]]]]]]],
            ['code' => 'BB', 'title' => 'منطقه ب', 'rank' => 9, 'members' => [['member_type' => 'CITY', 'city_id' => $cities[1]]]],
        ]], (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $zoneSet['zone_set_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'zone-sets', $zoneSet['zone_set_version_id'], 'publish', (string) Str::uuid());
        $zones = collect($zoneSet['zones'])->keyBy('code');
        $matrix = ['id' => (string) Str::uuid(), 'service_offering_version_id' => $offering['service_offering_version_id'], 'service_option_version_id' => null, 'origin_zone_id' => null, 'zone_ids' => [$zones['AA']['pricing_zone_id'], $zones['BB']['pricing_zone_id']], 'bands' => [
            ['id' => (string) Str::uuid(), 'from' => 0.5, 'to' => 2, 'cells' => [
                ['id' => (string) Str::uuid(), 'zone_id' => $zones['AA']['pricing_zone_id'], 'state' => 'RATE', 'amount' => 1000],
                ['id' => (string) Str::uuid(), 'zone_id' => $zones['BB']['pricing_zone_id'], 'state' => 'RATE', 'amount' => 5000],
            ]],
        ]];
        $matrix['linear_tail'] = ['id' => (string) Str::uuid(), 'from' => 2, 'step_kg' => 1, 'cells' => array_map(fn ($zone) => ['id' => (string) Str::uuid(), 'zone_id' => $zone, 'state' => 'RATE', 'amount' => 10000], $matrix['zone_ids'])];
        $tariff = $pricing->createTariff($maker, [
            'code' => 'V2_TARIFF', 'title' => 'تعرفه دوطرفه', 'currency' => 'IRR', 'purpose' => 'SALES', 'zone_set_version_id' => $zoneSet['zone_set_version_id'], 'zone_policy' => 'HIGHER_ZONE_RANK', 'valid_from' => now()->subMinute()->toISOString(), 'freight_matrices' => [$matrix],
            'rules' => [['service_offering_version_id' => $offering['service_offering_version_id'], 'charge_type_id' => DB::table('pricing_charge_types')->where('code', 'INSURANCE')->value('charge_type_id'), 'calculation_method' => 'PERCENT', 'basis' => 'DECLARED_VALUE', 'percentage_bps' => 2, 'amount_rounding_mode' => 'CEIL', 'amount_rounding_step' => 10000, 'priority' => 20, 'taxable' => false]],
        ], (string) Str::uuid());
        $input = ['service_offering_id' => $offering['service_offering_id'], 'service_offering_version_id' => $offering['service_offering_version_id'], 'sender' => ['city_id' => $cities[0], 'latitude' => 35.5, 'longitude' => 50.5], 'receiver' => ['city_id' => $cities[1], 'latitude' => 38, 'longitude' => 53], 'parcels' => [['weight_kg' => 1]], 'declared_value_amount' => 290000000, 'insurance_enabled' => true, 'cod_enabled' => false];
        $before = DB::table('pricing_quotes')->count();
        try { $missing = $input; unset($missing['sender']['latitude']); $pricing->simulateDraft($maker, $tariff['tariff_version_id'], $missing, 1); $this->fail('Polygon pricing requires actual coordinates.'); }
        catch (ApiException $error) { $this->assertSame(ApiErrorCode::PricingZoneUnresolved, $error->errorCode); }
        $forward = $pricing->simulateDraft($maker, $tariff['tariff_version_id'], $input, 1);
        $reverse = $pricing->simulateDraft($maker, $tariff['tariff_version_id'], [...$input, 'sender' => $input['receiver'], 'receiver' => $input['sender']], 1);
        $this->assertSame(65000, $forward['total_amount']);
        $this->assertSame($forward['total_amount'], $reverse['total_amount']);
        $this->assertSame(false, $forward['acceptable']);
        $this->assertArrayNotHasKey('quote_id', $forward);
        $this->assertSame($before, DB::table('pricing_quotes')->count());
        $this->assertSame(9, $forward['resolution_evidence']['zones']['basis']['rank']);
        $this->assertSame('HIGHER_ZONE_RANK', $forward['resolution_evidence']['zone_policy']);
        $multi = $pricing->simulateDraft($maker, $tariff['tariff_version_id'], [...$input, 'parcels' => [['weight_kg' => 0.5], ['weight_kg' => 0.5]]], 1);
        $this->assertSame(65000, $multi['total_amount']);
        $this->assertSame(2, $multi['resolution_evidence']['weight']['parcel_count']);
        foreach ([['parcels' => [['weight_kg' => 1, 'width_cm' => 10]]], ['insurance_enabled' => false], ['cod_enabled' => true, 'cod_amount' => 0]] as $invalid) {
            try { $pricing->simulateDraft($maker, $tariff['tariff_version_id'], [...$input, ...$invalid], 1); $this->fail('Invalid input must not simulate.'); } catch (ApiException $e) { $this->assertSame(ApiErrorCode::ValidationError, $e->errorCode); }
        }
        foreach ([[2, 65000], [2.1, 75000], [3, 75000], [3.1, 85000], [100, 1045000]] as [$weight, $total]) {
            $linear = $pricing->simulateDraft($maker, $tariff['tariff_version_id'], [...$input, 'parcels' => [['weight_kg' => $weight]]], 1);
            $this->assertSame($total, $linear['total_amount']);
        }
        $noRole = $this->user($tenant['hq_id'], 'v2-unprivileged');
        try { $pricing->simulateDraft(new AuthenticatedPrincipal($noRole['user_id'], (string) Str::uuid(), $tenant['hq_id'], false), $tariff['tariff_version_id'], $input, 1); $this->fail('Draft permission is required.'); } catch (ApiException $e) { $this->assertSame(ApiErrorCode::PermissionDenied, $e->errorCode); }
        $token = $this->login('v2-maker')['token'];
        $this->withToken($token)->postJson('/api/v1/admin/pricing/tariff-versions/'.$tariff['tariff_version_id'].'/simulate', [...$input, 'expected_version' => 1])->assertOk()->assertJsonPath('data.acceptable', false)->assertJsonPath('data.total_amount', 65000);

        try { $pricing->simulateDraft($maker, $tariff['tariff_version_id'], $input, 99); $this->fail('Stale draft should fail.'); } catch (ApiException $e) { $this->assertSame(ApiErrorCode::VersionConflict, $e->errorCode); }
        [, $foreign] = $this->administratorContext('V2-FOREIGN');
        try { $pricing->simulateDraft($foreign, $tariff['tariff_version_id'], $input, 1); $this->fail('Foreign draft should fail.'); } catch (ApiException $e) { $this->assertSame(ApiErrorCode::TenantAccessDenied, $e->errorCode); }
        $pricing->transition($maker, 'tariffs', $tariff['tariff_version_id'], 'approve', (string) Str::uuid());
        $pricing->transition($maker, 'tariffs', $tariff['tariff_version_id'], 'publish', (string) Str::uuid());
        $quote = $pricing->calculateQuote($maker, $input, (string) Str::uuid());
        $this->assertSame(65000, $quote['total_amount']);
        $linearQuote = $pricing->calculateQuote($maker, [...$input, 'parcels' => [['weight_kg' => 2.1]]], (string) Str::uuid());
        $this->assertSame(75000, $linearQuote['total_amount']);
        $this->assertEquals(1, $linearQuote['lines'][0]['explanation']['incremental_step_kg']);
        $this->assertSame($forward['zone_set_version_id'], $quote['zone_set_version_id']);
        $history = $pricing->history($maker, 'tariffs', $tariff['tariff_family_id']);
        $this->assertSame($quote['zone_set_version_id'], $history[0]['effective_zone_set']['zone_set_version_id']);
        try { DB::table('tariff_versions')->where('tariff_version_id', $tariff['tariff_version_id'])->update(['zone_policy' => 'DIRECTIONAL']); $this->fail('Published policy is immutable.'); } catch (QueryException $e) { $this->assertStringContainsString('immutable published Pricing', $e->getMessage()); }
        $this->assertSame($quote, $pricing->quoteDetail($maker, $quote['quote_id']));
        $targetId = (string) Str::uuid();
        DB::table('consignments')->insert([
            'consignment_id' => $targetId, 'hq_id' => $tenant['hq_id'], 'consignment_number' => 'POLYGON-SNAPSHOT', 'initiator_id' => $maker->userId, 'pickup_node_id' => $this->nodeIdForTenant($tenant['hq_id']),
            'sender_contact_name' => 'Sender', 'sender_mobile' => '09120000001', 'sender_address_text' => 'Address', 'sender_state' => 'State', 'sender_city' => 'City',
            'receiver_contact_name' => 'Receiver', 'receiver_mobile' => '09120000002', 'receiver_address_text' => 'Address', 'receiver_state' => 'State', 'receiver_city' => 'City',
            'service_type_id' => $type['service_type_id'], 'shipping_method_id' => $method['shipping_method_id'], 'weight_kg' => 1, 'declared_value_amount' => 290000000, 'insurance_value_amount' => 290000000, 'insurance_enabled' => true, 'cod_enabled' => false, 'payer' => 'SENDER', 'payment_method' => 'CASH', 'current_status' => 'CFM', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $snapshot = $pricing->acceptQuote($maker, $quote['quote_id'], 'CONSIGNMENT', $targetId, $quote['input_fingerprint'], (string) Str::uuid());
        $before = DB::table('pricing_snapshots')->where('pricing_snapshot_id', $snapshot['pricing_snapshot_id'])->first();
        $successor = $pricing->cloneDraft($maker, 'zone-sets', $zoneSet['pricing_zone_set_id'], (string) Str::uuid());
        $this->assertEquals(collect($zoneSet['zones'])->flatMap(fn ($z) => $z['members'])->firstWhere('member_type', 'POLYGON')['geometry'], collect($successor['zones'])->flatMap(fn ($z) => $z['members'])->firstWhere('member_type', 'POLYGON')['geometry']);
        $this->assertEquals($before, DB::table('pricing_snapshots')->where('pricing_snapshot_id', $snapshot['pricing_snapshot_id'])->first());

        $draft = $pricing->cloneDraft($maker, 'tariffs', $tariff['tariff_family_id'], (string) Str::uuid());
        $this->assertEquals($matrix['linear_tail'], $draft['freight_matrices'][0]['linear_tail']);
        $segments = $draft['freight_matrices'];
        $segments[0]['linear_bands'] = [];
        foreach ([[2, 50, 1, 10000], [50, 100, 0.5, 20000], [100, null, 1, 30000]] as [$from, $to, $step, $increment]) {
            $segments[0]['linear_bands'][] = ['id' => (string) Str::uuid(), 'from' => $from, 'to' => $to, 'step_kg' => $step, 'cells' => array_map(fn ($zone) => ['id' => (string) Str::uuid(), 'zone_id' => $zone, 'state' => 'RATE', 'amount' => $increment], $matrix['zone_ids'])];
        }
        $segments[0]['linear_tail'] = null;
        $this->withToken($token)->patchJson('/api/v1/admin/pricing/tariff-versions/'.$draft['tariff_version_id'], [...$draft, 'freight_matrices' => $segments, 'expected_version' => 1])->assertOk();
        foreach ([[50,545000], [50.1,565000], [100,2545000], [100.1,2575000]] as [$weight,$total]) {
            $this->assertSame($total, $pricing->simulateDraft($maker, $draft['tariff_version_id'], [...$input, 'parcels' => [['weight_kg' => $weight]]], 2)['total_amount']);
        }
        $this->assertEquals($segments[0]['linear_bands'], $pricing->tariffVersion($maker, $draft['tariff_version_id'])['freight_matrices'][0]['linear_bands']);
        $broken = $draft['freight_matrices']; $broken[0]['linear_tail']['cells'][0]['state'] = 'EMPTY'; $broken[0]['linear_tail']['cells'][0]['amount'] = null;
        $this->withToken($token)->patchJson('/api/v1/admin/pricing/tariff-versions/'.$draft['tariff_version_id'], [...$draft, 'freight_matrices' => $broken, 'expected_version' => 2])->assertOk();
        $this->withToken($token)->postJson('/api/v1/admin/pricing/tariffs/'.$draft['tariff_version_id'].'/approve')->assertStatus(422);
        $this->assertSame('DRAFT', $pricing->tariffVersion($maker, $draft['tariff_version_id'])['status']);
        $this->assertSame(75000, $pricing->quoteDetail($maker, $linearQuote['quote_id'])['total_amount']);
        try { $pricing->simulateDraft($maker, $tariff['tariff_version_id'], $input, 1); $this->fail('Published simulation must use runtime.'); } catch (ApiException $e) { $this->assertSame(ApiErrorCode::ValidationError, $e->errorCode); }
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

    public function test_zone_city_members_rehydrate_with_canonical_province_context(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [, $maker] = $this->administratorContext('ZONE-CITY-CONTEXT');
        $pricing = $this->app->make(PricingService::class);
        $cityProvinceId = GeographyIds::province('8');
        $cityIds = DB::table('cities')->where('province_id', $cityProvinceId)->where('is_active', true)->orderBy('legacy_city_code')->limit(2)->pluck('city_id')->map(fn ($id) => (string) $id)->all();
        $this->assertCount(2, $cityIds);
        $provinceMemberId = (string) DB::table('provinces')->where('province_id', '!=', $cityProvinceId)->where('is_active', true)->orderBy('legacy_province_code')->value('province_id');

        $created = $pricing->createZoneSet($maker, [
            'code' => 'CITY_CONTEXT', 'purpose' => 'SALES', 'title' => 'Canonical City context', 'valid_from' => null, 'valid_to' => null,
            'zones' => [[
                'code' => 'GROUPED', 'title' => 'Grouped cities', 'members' => [
                    ['member_type' => 'CITY', 'city_id' => $cityIds[0]],
                    ['member_type' => 'CITY', 'city_id' => $cityIds[1], 'province_id' => $cityProvinceId],
                    ['member_type' => 'PROVINCE', 'province_id' => $provinceMemberId],
                ],
            ]],
        ], (string) Str::uuid());

        $cityMembers = collect($created['zones'][0]['members'])->where('member_type', 'CITY')->values();
        $this->assertEqualsCanonicalizing($cityIds, $cityMembers->pluck('city_id')->all());
        $this->assertSame([$cityProvinceId, $cityProvinceId], $cityMembers->pluck('province_id')->all());
        $this->assertDatabaseHas('pricing_zone_members', ['member_type' => 'CITY', 'city_id' => $cityIds[0], 'province_id' => null]);
        $provinceMember = collect($created['zones'][0]['members'])->firstWhere('member_type', 'PROVINCE');
        $this->assertSame($provinceMemberId, $provinceMember['province_id']);
        $this->assertNull($provinceMember['city_id']);

        $saved = $pricing->updateZoneVersion($maker, $created['zone_set_version_id'], [
            'expected_version' => 1, 'valid_from' => null, 'valid_to' => null,
            'zones' => [[
                'code' => 'GROUPED', 'title' => 'Grouped cities', 'remote_area' => false,
                'members' => [...$cityMembers->map(fn ($member) => ['member_type' => 'CITY', 'city_id' => $member['city_id'], 'province_id' => $member['province_id']])->all(),
                    ['member_type' => 'PROVINCE', 'province_id' => $provinceMemberId],
                ],
            ]],
        ]);
        $reopened = $pricing->history($maker, 'zone-sets', $created['pricing_zone_set_id'])[0];
        $this->assertSame($saved['zone_set_version_id'], $reopened['zone_set_version_id']);
        $this->assertSame([$cityProvinceId, $cityProvinceId], collect($reopened['zones'][0]['members'])->where('member_type', 'CITY')->pluck('province_id')->values()->all());
        $this->assertSame($provinceMemberId, collect($reopened['zones'][0]['members'])->firstWhere('member_type', 'PROVINCE')['province_id']);

        $mismatchedProvinceId = (string) DB::table('provinces')->where('province_id', '!=', $cityProvinceId)->where('is_active', true)->orderByDesc('legacy_province_code')->value('province_id');
        try {
            $pricing->updateZoneVersion($maker, $created['zone_set_version_id'], [
                'expected_version' => 2, 'valid_from' => null, 'valid_to' => null,
                'zones' => [['code' => 'GROUPED', 'title' => 'Grouped cities', 'members' => [['member_type' => 'CITY', 'city_id' => $cityIds[0], 'province_id' => $mismatchedProvinceId]]]],
            ]);
            $this->fail('A mismatched City and Province must be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
            $this->assertSame(422, $exception->httpStatus);
        }
    }

    public function test_reusable_commitment_schedule_is_tenant_scoped_resolvable_and_immutable_after_publish(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenant, $maker, $checker, $nodeId] = $this->administratorContext('COMMITMENTS');
        $schedules = $this->app->make(CommitmentScheduleService::class);
        $monday = CarbonImmutable::parse('2026-08-17 07:00:00', 'Asia/Tehran');
        CarbonImmutable::setTestNow($monday);

        try {
            $draft = $schedules->create($maker, [
                'code' => 'TEHRAN_STANDARD', 'title' => 'برنامه استاندارد تهران',
                'timezone' => 'Asia/Tehran', 'calendar_code' => 'IR_STANDARD',
                'valid_from' => $monday->subMinute()->utc()->toISOString(), 'valid_to' => null,
                'windows' => [
                    ['window_code' => 'MORNING', 'window_type' => 'PICKUP', 'label_fa' => 'صبح', 'start_time' => '08:00', 'end_time' => '12:00', 'booking_cutoff_time' => '09:00', 'applicable_weekdays' => [1, 2, 3, 4, 5, 6], 'day_offset' => 0, 'active' => true],
                    ['window_code' => 'NEXT_DAY', 'window_type' => 'DELIVERY', 'label_fa' => 'روز بعد', 'start_time' => '09:00', 'end_time' => '17:00', 'booking_cutoff_time' => '23:59', 'applicable_weekdays' => [1, 2, 3, 4, 5, 6], 'day_offset' => 1, 'active' => true],
                ],
                'scopes' => [['scope_type' => 'NODE', 'node_id' => $nodeId]],
            ], (string) Str::uuid());
            $versionId = (string) $draft['commitment_schedule_version_id'];
            $this->assertSame(['valid' => true, 'errors' => []], $schedules->validate($maker, $versionId));
            $schedules->transition($checker, $versionId, 'approve', (string) Str::uuid());
            $published = $schedules->transition($maker, $versionId, 'publish', (string) Str::uuid());
            $this->assertSame('PUBLISHED', $published['status']);

            $windows = $schedules->pickupWindows($maker, $nodeId, $monday->utc()->toISOString());
            $this->assertCount(1, $windows);
            $this->assertSame('MORNING', $windows[0]['window_code']);
            $this->assertSame($versionId, $windows[0]['commitment_schedule_version_id']);

            [, $foreign] = $this->administratorContext('COMMITMENTS-FOREIGN');
            $this->assertSame([], $schedules->list($foreign, [])->items());
            try {
                $schedules->versionDetail($foreign, $versionId);
                $this->fail('A foreign tenant must not read the schedule version.');
            } catch (ApiException $exception) {
                $this->assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
            }

            try {
                DB::table('commitment_schedule_windows')->where('commitment_schedule_version_id', $versionId)->update(['label_fa' => 'دستکاری']);
                $this->fail('Published commitment windows must be immutable.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('immutable published commitment schedule child', $exception->getMessage());
            }
            $this->assertDatabaseHas('commitment_schedules', ['hq_id' => $tenant['hq_id'], 'code' => 'TEHRAN_STANDARD']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_selector_references_are_tenant_scoped_and_server_derived(): void
    {
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        [$tenantA, $actorA] = $this->administratorContext('SELECTOR-A');
        [$tenantB, $actorB] = $this->administratorContext('SELECTOR-B');
        $pricing = $this->app->make(PricingService::class);
        $catalog = $this->app->make(ServiceCatalogService::class);

        $makeZoneSet = function (AuthenticatedPrincipal $actor, string $code) use ($pricing): array {
            $created = $pricing->createZoneSet($actor, [
                'code' => $code, 'purpose' => 'SALES', 'title' => "Zone set {$code}", 'valid_from' => now()->subDay()->utc()->toISOString(), 'valid_to' => null,
                'zones' => [['code' => 'CENTER', 'title' => 'Center', 'members' => [['member_type' => 'CITY', 'city_id' => GeographyIds::city('10866')]]]],
            ], (string) Str::uuid());
            $pricing->transition($actor, 'zone-sets', $created['zone_set_version_id'], 'approve', (string) Str::uuid());
            $pricing->transition($actor, 'zone-sets', $created['zone_set_version_id'], 'publish', (string) Str::uuid());

            return $created;
        };
        $zoneSetA = $makeZoneSet($actorA, 'SELECTOR_A');
        $zoneSetB = $makeZoneSet($actorB, 'SELECTOR_B');

        $references = $pricing->listZoneSetVersionReferences($actorA, ['page_size' => 50]);
        $this->assertSame([$zoneSetA['zone_set_version_id']], array_column($references->items(), 'zone_set_version_id'));
        $this->assertSame($zoneSetA['zones'][0]['pricing_zone_id'], $references->items()[0]['zones'][0]['pricing_zone_id']);

        $unprivileged = $this->user($tenantA['hq_id'], 'selector-no-role');
        try {
            $pricing->listZoneSetVersionReferences(new AuthenticatedPrincipal($unprivileged['user_id'], (string) Str::uuid(), $tenantA['hq_id'], false), []);
            $this->fail('The reference endpoint service must require pricing.tariff.view.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PermissionDenied, $exception->errorCode);
        }

        $tariffInput = [
            'code' => 'SELECTOR_TARIFF', 'purpose' => 'SALES', 'currency' => 'IRR', 'scope_type' => 'TENANT', 'scope_value' => $tenantB['hq_id'], 'priority' => 100,
            'zone_set_version_id' => $zoneSetA['zone_set_version_id'], 'valid_from' => null, 'valid_to' => null,
            'volumetric_divisor' => 5000, 'weight_rounding_step_kg' => 0.5, 'rounding_mode' => 'STEP_UP', 'rules' => [],
        ];
        $tariff = $pricing->createTariff($actorA, $tariffInput, (string) Str::uuid());
        $automatic = $pricing->createTariff($actorA, [...$tariffInput, 'code' => null], (string) Str::uuid());
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{8}$/', $automatic['code']);
        $manual = $pricing->createTariff($actorA, [...$tariffInput, 'code' => '123456789'], (string) Str::uuid());
        $this->assertSame('123456789', $manual['code']);
        try {
            $pricing->createTariff($actorA, [...$tariffInput, 'code' => '123456789'], (string) Str::uuid());
            $this->fail('Duplicate numeric codes must return a conflict.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::Conflict, $exception->errorCode);
        }
        $this->assertDatabaseHas('tariff_families', [
            'tariff_family_id' => $tariff['tariff_family_id'], 'hq_id' => $tenantA['hq_id'], 'scope_type' => 'TENANT', 'scope_value' => $tenantA['hq_id'],
        ]);
        try {
            $pricing->createTariff($actorA, [...$tariffInput, 'code' => 'FOREIGN_ZONE', 'zone_set_version_id' => $zoneSetB['zone_set_version_id']], (string) Str::uuid());
            $this->fail('A foreign Zone Set version must not be accepted.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
        }

        $type = $catalog->createIdentity($actorA, 'service-types', [
            'code' => 'SELECTOR_TYPE', 'labels' => ['fa' => 'نوع انتخابی'], 'description' => null, 'definition' => [], 'valid_from' => null, 'valid_to' => null,
        ], (string) Str::uuid());
        $includedCurrent = $catalog->listPublishedVersions($actorA, 'service-types', ['include_version_ids' => [$type['service_type_version_id']]]);
        $this->assertSame($type['service_type_version_id'], $includedCurrent->items()[0]['service_type_version_id']);
        $this->assertSame('DRAFT', $includedCurrent->items()[0]['status']);
        $this->assertSame(0, $catalog->listPublishedVersions($actorB, 'service-types', ['include_version_ids' => [$type['service_type_version_id']]])->total());
        $method = $catalog->createIdentity($actorA, 'shipping-methods', [
            'code' => 'SELECTOR_METHOD', 'labels' => ['fa' => 'روش انتخابی'], 'description' => null, 'definition' => [], 'valid_from' => null, 'valid_to' => null,
        ], (string) Str::uuid());
        $areaA = (string) DB::table('nodes')->where('node_id', $this->nodeIdForTenant($tenantA['hq_id']))->value('area_id');
        $areaB = (string) DB::table('nodes')->where('node_id', $this->nodeIdForTenant($tenantB['hq_id']))->value('area_id');
        $offeringInput = [
            'code' => 'SELECTOR_OFFERING', 'labels' => ['fa' => 'خدمت انتخابی'], 'description' => null,
            'service_type_version_id' => $type['service_type_version_id'], 'shipping_method_version_id' => $method['shipping_method_version_id'],
            'sla_policy' => ['commitment_type' => 'DURATION', 'duration_value' => 1, 'duration_unit' => 'DAY'],
            'availability_bindings' => [['scope_type' => 'TENANT', 'scope_value' => $tenantB['hq_id'], 'enabled' => true]],
            'option_rules' => [], 'eligibility_rules' => [],
            'coverage_references' => [
                ['direction' => 'BOTH', 'reference_type' => 'COUNTRY', 'reference_value' => 'IR'],
                ['direction' => 'ORIGIN', 'reference_type' => 'PROVINCE', 'reference_value' => GeographyIds::province('8')],
                ['direction' => 'DESTINATION', 'reference_type' => 'CITY', 'reference_value' => GeographyIds::city('10866')],
                ['direction' => 'BOTH', 'reference_type' => 'OPERATIONAL_AREA', 'reference_value' => $areaA],
                ['direction' => 'LANE', 'reference_type' => 'PRICING_ZONE_SET', 'reference_value' => $zoneSetA['zone_set_version_id']],
                ['direction' => 'BOTH', 'reference_type' => 'POSTAL_RANGE', 'reference_value' => '1000000000', 'secondary_reference_value' => '1999999999'],
            ],
            'valid_from' => null, 'valid_to' => null,
        ];
        $offering = $catalog->createIdentity($actorA, 'offerings', $offeringInput, (string) Str::uuid());
        $this->assertDatabaseHas('service_availability_bindings', [
            'service_offering_version_id' => $offering['service_offering_version_id'], 'scope_type' => 'TENANT', 'scope_value' => $tenantA['hq_id'],
        ]);
        $this->assertDatabaseHas('service_coverage_references', [
            'service_offering_version_id' => $offering['service_offering_version_id'], 'reference_type' => 'OPERATIONAL_AREA', 'reference_value' => $areaA,
        ]);

        foreach ([
            [...$offeringInput, 'code' => 'FOREIGN_AREA', 'coverage_references' => [['direction' => 'BOTH', 'reference_type' => 'OPERATIONAL_AREA', 'reference_value' => $areaB]], 'availability_bindings' => [['scope_type' => 'TENANT', 'enabled' => true]]],
            [...$offeringInput, 'code' => 'UNSUPPORTED_SCOPE', 'coverage_references' => [], 'availability_bindings' => [['scope_type' => 'CUSTOMER', 'scope_value' => (string) Str::uuid(), 'enabled' => true]]],
        ] as $invalidInput) {
            try {
                $catalog->createIdentity($actorA, 'offerings', $invalidInput, (string) Str::uuid());
                $this->fail('Forged or unsupported references must be rejected.');
            } catch (ApiException $exception) {
                $this->assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
            }
        }
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

        $mismatchedProvinceId = (string) DB::table('provinces')->where('province_id', '!=', GeographyIds::province('8'))->where('is_active', true)->value('province_id');
        $this->withToken($token)->postJson('/api/v1/admin/pricing/zone-sets', [
            'code' => 'MISMATCHED_CITY_PROVINCE', 'purpose' => 'SALES', 'title' => 'Mismatched canonical geography',
            'zones' => [[
                'code' => 'INVALID_CITY_CONTEXT', 'title' => 'Invalid City context',
                'members' => [['member_type' => 'CITY', 'city_id' => GeographyIds::city('10866'), 'province_id' => $mismatchedProvinceId]],
            ]],
        ])->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');

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

    private function nodeIdForTenant(string $hqId): string
    {
        return (string) DB::table('nodes')->where('hq_id', $hqId)->orderBy('created_at')->value('node_id');
    }

    /** @param array<string,mixed> $type @param array<string,mixed> $method @param array<string,mixed> $offering @return array<string,mixed> */
    private function consignmentDraft(array $type, array $method, array $offering): array
    {
        return ['sender' => ['contact_name' => 'Sender', 'mobile' => '09120000001', 'address_text' => 'Tehran', 'city_id' => GeographyIds::city('10866'), 'state' => 'Tehran', 'city' => 'Tehran'], 'receiver' => ['contact_name' => 'Receiver', 'mobile' => '09120000002', 'address_text' => 'Tehran', 'city_id' => GeographyIds::city('10866'), 'state' => 'Tehran', 'city' => 'Tehran'], 'service_type_id' => $type['service_type_id'], 'shipping_method_id' => $method['shipping_method_id'], 'service_offering_id' => $offering['service_offering_id'], 'service_offering_version_id' => $offering['service_offering_version_id'], 'selected_option_version_ids' => [], 'pickup_service_date' => now('Asia/Tehran')->toDateString(), 'pickup_window_code' => 'MORNING', 'delivery_window_code' => null, 'pickup_commitment_at' => now()->addHour()->utc()->toISOString(), 'delivery_commitment_at' => now()->addDay()->utc()->toISOString(), 'weight_kg' => 1.1, 'width_cm' => 10, 'length_cm' => 10, 'height_cm' => 10, 'declared_value_amount' => 100000, 'insurance_enabled' => true, 'insurance_value_amount' => 100000, 'cod_enabled' => false, 'cod_amount' => null, 'payer' => 'SENDER', 'payment_method' => 'CASH', 'parcels' => [['content_description' => 'کالا', 'weight_kg' => 1.1, 'width_cm' => 10, 'length_cm' => 10, 'height_cm' => 10]]];
    }
}
