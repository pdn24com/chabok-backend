<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Consignment\Application\ConsignmentService;
use Modules\Consignment\Application\ConsignmentNumberRangeService;
use Modules\Consignment\Application\Contracts\PricingQuoteProvider;
use Modules\Consignment\Application\PricingService;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Geography\Domain\GeographyIds;
use Modules\Geography\Infrastructure\Database\Seeders\IranGeographySeeder;

final class ConsignmentIntegrationTest extends MySqlRedisTestCase
{
    private static int $rangeSequence = 100000;
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(IranGeographySeeder::class)->run();
        $this->app->make(AuthorizationCatalogSeeder::class)->run();
        $this->app->instance(PricingQuoteProvider::class, new class implements PricingQuoteProvider {
            public function calculate(array $normalizedInput): array
            {
                return [[
                    'external_method_code' => '7',
                    'method_name' => 'Sanitized test method',
                    'icon' => null,
                    'external_price_list_code' => '4',
                    'zone' => '2',
                    'available' => true,
                    'unavailable_reason' => null,
                    'currency' => 'IRR',
                    'total_amount' => 2500,
                    'charge_lines' => [
                        ['charge_code' => 'LEGACY_MANUAL_COST', 'title' => 'Manual cost', 'amount' => 2000],
                        ['charge_code' => 'LEGACY_MANUAL_VAT', 'title' => 'VAT', 'amount' => 500],
                    ],
                    'min_ins' => 100,
                    'delivery_windows' => [[
                        'gregorian_date' => '2026-08-01',
                        'jalali_display_date' => null,
                        'persian_weekday_label' => null,
                        'persian_month_label' => null,
                        'time_ranges' => ['11:00 - 13:00'],
                    ]],
                ]];
            }
        });
    }

    public function test_create_list_detail_and_repriced_edit_are_atomic_scoped_and_immutable(): void
    {
        [$tenant, $actor, $node, $principal] = $this->branchContext('CONSIGN-A', 'manager-a');
        $draft = $this->draft();
        $pricing = $this->app->make(PricingService::class);
        $quote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $rawRedis = (string) Redis::connection('cache')->get("chabok:consignment:quote:{$quote['quote_id']}");
        $this->assertNotSame('', $rawRedis);
        $this->assertStringNotContainsString('Sanitized test method', $rawRedis);

        $created = $this->app->make(ConsignmentService::class)->create(
            $principal,
            $node,
            [...$draft, 'accepted_quote' => [
                'quote_id' => $quote['quote_id'],
                'quote_version' => $quote['quote_version'],
                'option_id' => $quote['options'][0]['option_id'],
            ]],
            '11111111-2222-4333-8444-555555555555',
        );

        $this->assertSame('CFM', $created['current_status']);
        $this->assertSame(1, $created['version']);
        $this->assertCount(2, $created['parcels']);
        $this->assertSame('اسناد', $created['parcels'][0]['content_description']);
        $this->assertNull($created['parcels'][1]['content_description']);
        $this->assertMatchesRegularExpression('/^[0-9]{12}$/', $created['consignment_number']);
        $this->assertSame("{$created['consignment_number']}-01", $created['parcels'][0]['parcel_number']);
        $this->assertSame(2500, $created['accepted_pricing_versions'][0]['total_amount']);
        $this->assertDatabaseCount('consignments', 1);
        $this->assertDatabaseCount('parcels', 2);
        $this->assertDatabaseCount('consignment_pricing_versions', 1);
        $this->assertDatabaseCount('consignment_pricing_charge_lines', 2);
        $this->assertDatabaseCount('consignment_status_events', 3);
        $this->assertSame(3, DB::table('consignment_status_events')
            ->where('correlation_id', '11111111-2222-4333-8444-555555555555')->count());
        $this->assertDatabaseHas('audit_events', ['action_key' => 'CONSIGNMENT_CREATED']);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'consignment.created']);
        try {
            DB::table('consignment_number_allocations')->where('consignment_id', $created['consignment_id'])->update(['consignment_number' => '999999']);
            $this->fail('The number allocation ledger must be immutable.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('immutable Consignment number allocation', $exception->getMessage());
        }

        $page = $this->app->make(ConsignmentService::class)->list(
            $principal,
            $node,
            ['page' => 1, 'page_size' => 25, 'search' => $created['parcels'][1]['parcel_number']],
        );
        $this->assertSame(1, $page->total());
        $this->assertSame(2500, (int) $page->items()[0]->payable_total_amount);
        $this->assertSame('IRR', $page->items()[0]->payable_currency);

        $editedDraft = $draft;
        $editedDraft['receiver']['address_text'] = 'Updated safe address';
        $editedDraft['sender']['contact_name'] = 'Corrected sender';
        $editedDraft['payer'] = 'RECEIVER';
        $editedDraft['payment_method'] = 'CREDIT';
        $editedDraft['parcels'][0]['weight_kg'] = 3;
        $editedDraft['parcels'][0]['content_description'] = 'Edited parcel content';
        $parcelChanges = array_map(fn ($parcel, $index) => [...array_fill_keys(['content_description', 'weight_kg', 'width_cm', 'length_cm', 'height_cm'], null), ...$parcel, 'parcel_id' => $created['parcels'][$index]['parcel_id']], $editedDraft['parcels'], array_keys($editedDraft['parcels']));
        $invalidParcels = $parcelChanges;
        $invalidParcels[0]['parcel_id'] = (string) Str::uuid();
        try {
            $this->app->make(ConsignmentService::class)->edit($principal, $node, $created['consignment_id'], ['expected_version' => 1, 'change_reason' => 'Invalid parcel', 'parcels' => $invalidParcels], (string) Str::uuid());
            $this->fail('Foreign parcel IDs must be rejected.');
        } catch (ApiException $error) {
            $this->assertSame(422, $error->httpStatus);
        }
        $editQuote = $pricing->calculate(
            $principal,
            $node,
            'EDIT',
            $editedDraft,
            $created['consignment_id'],
            1,
        );
        $edited = $this->app->make(ConsignmentService::class)->edit(
            $principal,
            $node,
            $created['consignment_id'],
            [
                'expected_version' => 1,
                'change_reason' => 'Receiver correction',
                'sender' => $editedDraft['sender'],
                'payer' => $editedDraft['payer'],
                'payment_method' => $editedDraft['payment_method'],
                'parcels' => $parcelChanges,
                'receiver' => $editedDraft['receiver'],
                'accepted_quote' => [
                    'quote_id' => $editQuote['quote_id'],
                    'quote_version' => 1,
                    'option_id' => $editQuote['options'][0]['option_id'],
                ],
            ],
            '22222222-3333-4444-8555-666666666666',
        );
        $this->assertSame(2, $edited['version']);
        $this->assertSame('Updated safe address', $edited['receiver']['address_text']);
        $this->assertSame('Corrected sender', $edited['sender']['contact_name']);
        $this->assertSame('RECEIVER', $edited['payer']);
        $this->assertSame('CREDIT', $edited['payment_method']);
        $this->assertSame(3.0, $edited['parcels'][0]['weight_kg']);
        $this->assertSame('Edited parcel content', $edited['parcels'][0]['content_description']);
        $this->assertSame($created['parcels'][0]['parcel_id'], $edited['parcels'][0]['parcel_id']);
        $this->assertSame($created['parcels'][0]['current_status'], $edited['parcels'][0]['current_status']);
        $this->assertCount(2, $edited['accepted_pricing_versions']);
        $this->assertCount(2, $edited['parcels']);

        try {
            DB::table('consignment_pricing_versions')
                ->where('consignment_id', $created['consignment_id'])
                ->update(['total_amount' => 1]);
            $this->fail('Accepted pricing must be immutable.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('immutable Consignment history', $exception->getMessage());
        }

        try {
            $this->app->make(ConsignmentService::class)->edit(
                $principal,
                $node,
                $created['consignment_id'],
                [
                    'expected_version' => 1,
                    'change_reason' => 'Stale edit',
                    'receiver' => $editedDraft['receiver'],
                    'accepted_quote' => [
                        'quote_id' => $editQuote['quote_id'],
                        'quote_version' => 1,
                        'option_id' => $editQuote['options'][0]['option_id'],
                    ],
                ],
                (string) Str::uuid(),
            );
            $this->fail('Stale edit must fail.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::VersionConflict, $exception->errorCode);
        }

        [, , $foreignNode, $foreignPrincipal] = $this->branchContext('CONSIGN-B', 'manager-b');
        try {
            $this->app->make(ConsignmentService::class)->get(
                $foreignPrincipal,
                $foreignNode,
                $created['consignment_id'],
            );
            $this->fail('Cross-tenant guessed IDs must not resolve.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
        }

        $this->assertDatabaseHas('consignments', [
            'hq_id' => $tenant['hq_id'],
            'consignment_id' => $created['consignment_id'],
            'version' => 2,
        ]);
    }

    public function test_permission_lifecycle_and_quote_context_negatives_fail_without_mutation(): void
    {
        [, $actor, $node, $principal] = $this->branchContext('CONSIGN-N', 'manager-n');
        $draft = $this->draft();
        $pricing = $this->app->make(PricingService::class);
        $quote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $draft['declared_value_amount']++;
        $draft['insurance_value_amount']++;
        try {
            $this->app->make(ConsignmentService::class)->create(
                $principal,
                $node,
                [...$draft, 'accepted_quote' => [
                    'quote_id' => $quote['quote_id'],
                    'quote_version' => 1,
                    'option_id' => $quote['options'][0]['option_id'],
                ]],
                (string) Str::uuid(),
            );
            $this->fail('Fingerprint mismatch must reject mutation.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PricingQuoteMismatch, $exception->errorCode);
        }
        $this->assertDatabaseCount('consignments', 0);

        $roleId = (string) DB::table('roles')->where('role_code', 'branch_read_only')->value('role_id');
        DB::table('user_role_assignments')->where('user_id', $actor['user_id'])->update([
            'role_id' => $roleId,
            'active_slot' => hash('sha256', "{$actor['user_id']}|{$roleId}|TENANT|-"),
        ]);
        $this->app->make(\Modules\Authorization\Application\AuthorizationService::class)
            ->invalidateUser($actor['user_id']);
        try {
            $pricing->calculate($principal, $node, 'CREATE', $this->draft(), null, null);
            $this->fail('Read-only users cannot price Create.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PermissionDenied, $exception->errorCode);
        }

        $managerRoleId = (string) DB::table('roles')->where('role_code', 'branch_manager')->value('role_id');
        DB::table('user_role_assignments')->where('user_id', $actor['user_id'])->update([
            'role_id' => $managerRoleId,
            'active_slot' => hash('sha256', "{$actor['user_id']}|{$managerRoleId}|TENANT|-"),
        ]);
        DB::table('tenant_module_entitlements')->where([
            'hq_id' => $actor['hq_id'],
            'module_code' => 'Consignment',
        ])->update(['status' => 'DISABLED']);
        $this->app->make(\Modules\Authorization\Application\AuthorizationService::class)
            ->invalidateUser($actor['user_id']);
        try {
            $pricing->calculate($principal, $node, 'CREATE', $this->draft(), null, null);
            $this->fail('Disabled Consignment entitlement must deny pricing.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::EntitlementDisabled, $exception->errorCode);
        }
    }

    public function test_operational_status_catalog_is_tenant_scoped_versioned_and_preserves_history(): void
    {
        [, $user, $node, $actor] = $this->branchContext('STATUS-A','status-admin-a');
        $catalog=$this->app->make(\Modules\Consignment\Application\OperationalStatusCatalog::class);
        $input=['code'=>'CUSTOM_A','scope'=>'TENANT','title_fa'=>'وضعیت اختصاصی','title_en'=>null,'partial_title_fa'=>null,'partial_title_en'=>null,'tone'=>'brand','status_group'=>'IN_OPERATION','is_terminal'=>false,'is_active'=>true,'sort_order'=>100];
        try { $catalog->save($actor,null,$input,(string)Str::uuid()); self::fail('Read-only catalogue access must not permit writes'); } catch(ApiException $e) { self::assertSame(403,$e->httpStatus); }
        $permission=DB::table('permissions')->where('permission_code','operational_status.manage')->value('permission_id');
        $role=DB::table('user_role_assignments')->where('user_id',$user['user_id'])->value('role_id');
        DB::table('role_permissions')->insert(['role_permission_id'=>(string)Str::uuid(),'role_id'=>$role,'permission_id'=>$permission,'created_at'=>now()]);
        $this->app->make(\Modules\Authorization\Application\AuthorizationService::class)->invalidateUser($user['user_id']);
        $created=$catalog->save($actor,null,$input,(string)Str::uuid());
        self::assertTrue($created['can_manage']); self::assertSame(1,$created['version']);
        [, , $otherNode, $other] = $this->branchContext('STATUS-B','status-admin-b');
        self::assertNotContains('CUSTOM_A',array_column($catalog->entries($other),'code'));
        try { $catalog->save($other,$created['status_id'],[...$input,'expected_version'=>1],(string)Str::uuid()); self::fail(); } catch(ApiException $e) { self::assertSame(403,$e->httpStatus); }
        foreach (['GLOBAL','TENANT'] as $scope) {
            try { $catalog->save($actor,null,[...$input,'scope'=>$scope,'code'=>'CFM'],(string)Str::uuid()); self::fail(); } catch(ApiException $e) { self::assertContains($e->httpStatus,[403,409]); }
        }
        $updated=$catalog->save($actor,$created['status_id'],[...$input,'expected_version'=>1,'title_fa'=>'عنوان جدید','is_active'=>false],(string)Str::uuid());
        self::assertSame(2,$updated['version']); self::assertFalse($updated['is_active']);
        self::assertSame(2,$catalog->save($actor,$created['status_id'],[...$input,'expected_version'=>2,'title_fa'=>'عنوان جدید','is_active'=>false],(string)Str::uuid())['version']);
        self::assertSame(2,DB::table('operational_status_revisions')->where('status_id',$created['status_id'])->count());
        try { $catalog->save($actor,$created['status_id'],[...$input,'expected_version'=>1],(string)Str::uuid()); self::fail(); } catch(ApiException $e) { self::assertSame(409,$e->httpStatus); }
        $otherStatus=$catalog->save($other,null,[...$input,'code'=>'OTHER_ONLY'],(string)Str::uuid());
        self::assertNotContains('OTHER_ONLY',array_column($catalog->entries($actor),'code'));
        $draft=$this->draft(); $quote=$this->app->make(PricingService::class)->calculate($actor,$node,'CREATE',$draft,null,null);
        $consignment=$this->app->make(ConsignmentService::class)->create($actor,$node,[...$draft,'accepted_quote'=>['quote_id'=>$quote['quote_id'],'quote_version'=>1,'option_id'=>$quote['options'][0]['option_id']]],(string)Str::uuid());
        $events=DB::table('consignment_status_events')->where('consignment_id',$consignment['consignment_id'])->get()->all();
        DB::table('consignments')->where('consignment_id',$consignment['consignment_id'])->update(['current_status'=>'CUSTOM_A']);
        self::assertSame(1,$this->app->make(ConsignmentService::class)->list($actor,$node,['status'=>'CUSTOM_A'])->total());
        try { DB::table('consignments')->where('consignment_id',$consignment['consignment_id'])->update(['current_status'=>'OTHER_ONLY']); self::fail(); } catch(\Illuminate\Database\QueryException $e) { self::assertStringContainsString('Unknown operational status for tenant',$e->getMessage()); }
        self::assertEquals($events,DB::table('consignment_status_events')->where('consignment_id',$consignment['consignment_id'])->get()->all());
    }

    public function test_multi_selection_consignment_list_contract_scope_and_columns(): void
    {
        [$tenant, , $node, $principal] = $this->branchContext('MULTI-C', 'multi-c');
        $service = $this->app->make(ConsignmentService::class);
        $ids = [];
        foreach (['PU', 'IR', 'OF'] as $index => $status) {
            $draft = $this->draft();
            $quote = $this->app->make(PricingService::class)->calculate($principal, $node, 'CREATE', $draft, null, null);
            $created = $service->create($principal, $node, [...$draft, 'accepted_quote' => [
                'quote_id'=>$quote['quote_id'], 'quote_version'=>1, 'option_id'=>$quote['options'][0]['option_id'],
            ]], (string) Str::uuid());
            $ids[] = $created['consignment_id'];
            DB::table('consignments')->where('consignment_id', $created['consignment_id'])->update([
                'current_status'=>$status, 'created_at'=>'2026-09-'.(10+$index).' 12:00:00',
                'delivery_commitment_at'=>$index === 0 ? '2020-01-01 10:00:00' : '2035-01-01 10:00:00',
            ]);
        }
        $driver = (string) Str::uuid();
        DB::table('drivers')->insert(['driver_id'=>$driver,'hq_id'=>$tenant['hq_id'],'driver_code'=>'MULTI','display_name'=>'Named driver','home_node_id'=>$node,'operational_type'=>'PICKUP','status'=>'ACTIVE','availability_status'=>'AVAILABLE','version'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('consignments')->where('consignment_id',$ids[0])->update(['pickup_man_id'=>$driver]);
        $requestList = function (array $filters) use ($node, $principal): array {
            $request = \Illuminate\Http\Request::create('/api/v1/consignments', 'GET', $filters);
            $request->attributes->set('principal', $principal);
            $request->attributes->set('node_id', $node);
            return $this->app->make(\Modules\Consignment\Infrastructure\Http\ConsignmentController::class)->index($request)->getData(true);
        };
        $filters = ['status'=>'PU,IR,PU','pickup_node_id'=>$node.','.(string) Str::uuid(), 'created_from'=>'2026-09-10T00:00:00Z', 'created_to'=>'2026-09-11T23:59:59Z', 'page_size'=>1];
        $first = $requestList($filters);
        $second = $requestList([...$filters,'page'=>2]);
        self::assertSame(2,$first['meta']['pagination']['total']);
        self::assertNotSame($first['data'][0]['consignment_id'],$second['data'][0]['consignment_id']);
        self::assertSame('Named driver',$second['data'][0]['pickup_man_title']);
        self::assertSame(2,$second['data'][0]['parcel_count']);
        self::assertSame(1,$requestList(['status'=>'PU'])['meta']['pagination']['total']);
        self::assertSame(3,$requestList(['status'=>''])['meta']['pagination']['total']);
        self::assertSame(2,$requestList(['status'=>'PU,IR','sla_risk'=>'OVERDUE,ON_TIME'])['meta']['pagination']['total']);
        self::assertSame(1,$requestList(['status'=>'PU,IR','pickup_man_id'=>$driver])['meta']['pagination']['total']);
        self::assertSame(0,$requestList(['status'=>'PU,IR','delivery_node_id'=>(string) Str::uuid()])['meta']['pagination']['total']);
        foreach (['UNKNOWN', 'PU,,IR', array_fill(0,2,'PU'), implode(',',array_fill(0,51,'PU'))] as $invalid) {
            try { $requestList(['status'=>$invalid]); self::fail('Invalid selection accepted'); }
            catch (\Illuminate\Validation\ValidationException $error) { self::assertNotEmpty($error->errors()); }
        }
        [, , $otherNode, $otherPrincipal] = $this->branchContext('MULTI-C-OTHER','multi-c-other');
        self::assertSame(0,$service->list($otherPrincipal,$otherNode,['status'=>['PU','IR'],'pickup_node_id'=>[$node,$otherNode]])->total());
        try { $service->list($principal,$otherNode,['status'=>['PU','IR']]); self::fail('Foreign selected node accepted'); }
        catch (ApiException $error) { self::assertSame(ApiErrorCode::ScopeAccessDenied,$error->errorCode); }
    }

    public function test_list_filters_use_frozen_risk_threshold_and_scoped_agents(): void
    {
        [$tenant, , $node, $principal] = $this->branchContext('FILTER-SLA', 'filter-manager');
        $service = $this->app->make(ConsignmentService::class);
        $draft = $this->draft();
        $quote = $this->app->make(PricingService::class)->calculate($principal, $node, 'CREATE', $draft, null, null);
        $created = $service->create($principal, $node, [...$draft, 'accepted_quote' => ['quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id']]], (string) Str::uuid());
        $driver = (string) Str::uuid();
        DB::table('drivers')->insert(['driver_id'=>$driver,'hq_id'=>$principal->hqId,'driver_code'=>'FILTER-DRIVER','display_name'=>'Filter driver','home_node_id'=>$node,'operational_type'=>'PICKUP','status'=>'ACTIVE','availability_status'=>'AVAILABLE','version'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $fixed = \Carbon\CarbonImmutable::parse('2026-09-15T10:00:00Z');
        \Carbon\CarbonImmutable::setTestNow($fixed);
        try {
            DB::table('consignments')->where('consignment_id',$created['consignment_id'])->update(['pickup_man_id'=>$driver,'pickup_commitment_at'=>'2026-09-15 11:00:00','commitment_snapshot'=>json_encode(['pickup'=>['risk_threshold_minutes'=>60]])]);
            self::assertSame(1,$service->list($principal,$node,['sla_risk'=>'AT_RISK'])->total());
            self::assertSame(0,$service->list($principal,$node,['sla_risk'=>'ON_TIME'])->total());
            self::assertSame(1,$service->list($principal,$node,['pickup_man_id'=>$driver])->total());
            self::assertSame(0,$service->list($principal,$node,['delivery_man_id'=>$driver])->total());
            self::assertSame($driver,$service->filterOptions($principal,$node)['pickup_agents'][0]->value);
            DB::table('consignments')->where('consignment_id',$created['consignment_id'])->update(['pickup_commitment_at'=>'2026-09-15 11:00:01']);
            self::assertSame(1,$service->list($principal,$node,['sla_risk'=>'ON_TIME'])->total());
            DB::table('consignments')->where('consignment_id',$created['consignment_id'])->update(['pickup_commitment_at'=>'2026-09-15 09:59:59']);
            self::assertSame(1,$service->list($principal,$node,['sla_risk'=>'OVERDUE'])->total());
            DB::table('consignments')->where('consignment_id',$created['consignment_id'])->update(['current_status'=>'OK']);
            self::assertSame(0,$service->list($principal,$node,['sla_risk'=>'OVERDUE'])->total());
            [, , $otherNode, $otherPrincipal] = $this->branchContext('FILTER-OTHER', 'other-filter-manager');
            self::assertSame([], $service->filterOptions($otherPrincipal,$otherNode)['pickup_agents']);
            self::assertSame(0,$service->list($otherPrincipal,$otherNode,['pickup_man_id'=>$driver])->total());
        } finally { \Carbon\CarbonImmutable::setTestNow(); }
    }

    public function test_pricing_relevant_edit_without_a_quote_marks_pricing_stale_without_changing_status(): void
    {
        [, , $node, $principal] = $this->branchContext('CONSIGN-STALE', 'manager-stale');
        $draft = $this->draft();
        $pricing = $this->app->make(PricingService::class);
        $quote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $created = $this->app->make(ConsignmentService::class)->create($principal, $node, [...$draft, 'accepted_quote' => [
            'quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id'],
        ]], (string) Str::uuid());

        $edited = $this->app->make(ConsignmentService::class)->edit($principal, $node, $created['consignment_id'], [
            'expected_version' => 1,
            'change_reason' => 'Weight correction pending repricing',
            'weight_kg' => $draft['weight_kg'] + 1,
        ], (string) Str::uuid());

        $this->assertSame('CFM', $edited['current_status']);
        $this->assertSame('STALE', $edited['commercial_pricing_state']);
        $this->assertCount(1, $edited['accepted_pricing_versions']);
        $this->assertDatabaseHas('outbox_events', ['aggregate_id' => $created['consignment_id'], 'event_type' => 'consignment.pricing.stale']);
    }

    public function test_inactive_canonical_city_is_rejected_and_legacy_snapshots_remain_readable(): void
    {
        [, , $node, $principal] = $this->branchContext('CONSIGN-GEO', 'manager-geo');
        $draft = $this->draft();
        DB::table('cities')->where('city_id', GeographyIds::city('10866'))->update(['is_active' => false]);
        try {
            $this->app->make(PricingService::class)->calculate($principal, $node, 'CREATE', $draft, null, null);
            $this->fail('Inactive cities must be rejected before pricing.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
        }

        DB::table('cities')->where('city_id', GeographyIds::city('10866'))->update(['is_active' => true]);
        $pricing = $this->app->make(PricingService::class);
        $quote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $created = $this->app->make(ConsignmentService::class)->create($principal, $node, [...$draft, 'accepted_quote' => [
            'quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id'],
        ]], (string) Str::uuid());
        $this->assertSame('10866', $created['sender']['city_reference']['legacy_city_code']);
        $this->assertSame('تهران', $created['sender']['city']);

        DB::table('consignments')->where('consignment_id', $created['consignment_id'])->update([
            'sender_city_id' => null,
            'receiver_city_id' => null,
        ]);
        $legacy = $this->app->make(ConsignmentService::class)->get($principal, $node, $created['consignment_id']);
        $this->assertNull($legacy['sender']['city_id']);
        $this->assertNull($legacy['sender']['city_reference']);
        $this->assertSame('تهران', $legacy['sender']['city']);
    }

    public function test_api_envelopes_idempotency_validation_and_real_route_integration(): void
    {
        $context = $this->branchContext('CONSIGN-API', 'manager-api');
        $node = $context[2];
        $login = $this->login('manager-api');
        $draft = $this->draft();
        $quoteResponse = $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->postJson('/api/v1/consignments/pricing-quotes', [
                'purpose' => 'CREATE',
                ...$draft,
            ])->assertOk()->assertJsonStructure(['data', 'meta', 'correlation_id']);
        $serializedQuote = json_encode($quoteResponse->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('method_no', $serializedQuote);
        $this->assertStringNotContainsString('fld_Total_Cost', $serializedQuote);
        $quote = $quoteResponse->json('data');
        $payload = [...$draft, 'accepted_quote' => [
            'quote_id' => $quote['quote_id'],
            'quote_version' => $quote['quote_version'],
            'option_id' => $quote['options'][0]['option_id'],
        ]];
        $key = 'consignment-api-key-000001';
        $first = $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/consignments', $payload)
            ->assertCreated()->assertJsonStructure(['data', 'meta', 'correlation_id']);
        $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/consignments', $payload)
            ->assertCreated()->assertExactJson($first->json());
        $id = (string) $first->json('data.consignment_id');
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
            ->getJson('/api/v1/consignments')->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('meta.status_counts.total', 1)
            ->assertJsonPath('meta.status_counts.new_routed', 1)
            ->assertJsonPath('meta.status_counts.unassigned', 1)
            ->assertJsonPath('meta.status_counts.assigned', 0)
            ->assertJsonPath('data.0.pickup_node_title', 'Branch node')
            ->assertJsonPath('data.0.delivery_node_title', null)
            ->assertJsonPath('data.0.pickup_man_id', null)
            ->assertJsonPath('data.0.delivery_man_id', null);
        $this->withToken($login['token'])->withHeader('X-Node-Id', $node)
            ->getJson("/api/v1/consignments/{$id}")->assertOk()
            ->assertJsonPath('data.consignment_id', $id);

        $payload['accepted_quote']['unexpected_token'] = 'must-not-be-accepted';
        $this->withToken($login['token'])
            ->withHeader('X-Node-Id', $node)
            ->withHeader('Idempotency-Key', 'consignment-api-key-000002')
            ->postJson('/api/v1/consignments', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR');
    }

    public function test_range_validation_is_non_persisting_and_creation_is_available_overlap_safe_and_tenant_scoped(): void
    {
        [, $actor, , $principal] = $this->branchContext('RANGE-ADMIN-A', 'range-admin-a');
        $service = $this->app->make(ConsignmentNumberRangeService::class);
        try {
            $service->validate($principal, ['numeric_prefix' => '777001', 'total_length' => 12, 'serial_start' => '1', 'serial_end' => '9']);
            $this->fail('A Consignment creator without range-management permission must be denied administration.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PermissionDenied, $exception->errorCode);
        }
        $this->assignRole($actor['user_id'], 'hq_admin');
        $input = ['title' => 'HQ labels', 'numeric_prefix' => '777001', 'total_length' => 12, 'serial_start' => '1', 'serial_end' => '999999'];
        $before = DB::table('consignment_number_ranges')->count();
        $preview = $service->validate($principal, $input);
        $this->assertSame('VALID', $preview['validation_result']);
        $this->assertSame('777001000001', $preview['first_number']);
        $this->assertSame($before, DB::table('consignment_number_ranges')->count());

        $created = $service->create($principal, $input, (string) Str::uuid());
        $this->assertSame('AVAILABLE', $created['status']);
        $this->assertSame('0', $created['allocated_count']);
        $this->assertSame('999999', $created['remaining_count']);
        $this->assertDatabaseHas('audit_events', ['action_key' => 'CONSIGNMENT_NUMBER_RANGE_CREATED']);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'consignment.number-range.created']);

        $overlap = $service->validate($principal, [...$input, 'numeric_prefix' => '777001', 'serial_start' => '2']);
        $this->assertSame('OVERLAP', $overlap['validation_result']);
        try {
            $service->create($principal, [...$input, 'title' => 'Conflict'], (string) Str::uuid());
            $this->fail('Overlapping global ranges must be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ConsignmentNumberRangeOverlap, $exception->errorCode);
        }

        [, $foreignActor, , $foreignPrincipal] = $this->branchContext('RANGE-ADMIN-B', 'range-admin-b');
        $this->assignRole($foreignActor['user_id'], 'hq_admin');
        try {
            $service->range($foreignPrincipal, $created['range_id']);
            $this->fail('Cross-HQ range identifiers must not resolve.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
        }
    }

    public function test_final_number_exhausts_atomically_and_no_monthly_fallback_is_used(): void
    {
        [, , $node, $principal] = $this->branchContext('RANGE-LAST', 'range-last');
        DB::table('consignment_number_ranges')->where('hq_id', $principal->hqId)->update([
            'serial_end' => '000001', 'last_number' => DB::raw("CONCAT(numeric_prefix, '000001')"),
        ]);
        $draft = $this->draft();
        $pricing = $this->app->make(PricingService::class);
        $quote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $created = $this->app->make(ConsignmentService::class)->create($principal, $node, [...$draft, 'accepted_quote' => [
            'quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id'],
        ]], (string) Str::uuid());
        $this->assertMatchesRegularExpression('/^[0-9]{12}$/', $created['consignment_number']);
        $this->assertDatabaseHas('consignment_number_ranges', ['hq_id' => $principal->hqId, 'status' => 'EXHAUSTED', 'next_serial' => null]);
        $this->assertDatabaseHas('consignment_number_allocations', ['consignment_id' => $created['consignment_id'], 'consignment_number' => $created['consignment_number']]);

        $secondQuote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        try {
            $this->app->make(ConsignmentService::class)->create($principal, $node, [...$draft, 'accepted_quote' => [
                'quote_id' => $secondQuote['quote_id'], 'quote_version' => 1, 'option_id' => $secondQuote['options'][0]['option_id'],
            ]], (string) Str::uuid());
            $this->fail('Exhaustion must fail closed.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ConsignmentNumberRangeExhausted, $exception->errorCode);
        }
        $this->assertDatabaseCount('consignment_number_sequences', 0);
        $this->assertDatabaseCount('consignment_number_allocations', 1);
    }

    public function test_disabled_ranges_are_skipped_and_fifo_rolls_over_to_the_next_available_range(): void
    {
        [, $actor, $node, $principal] = $this->branchContext('RANGE-ROLL', 'range-roll');
        DB::table('consignment_number_ranges')->where('hq_id', $principal->hqId)->update([
            'status' => 'DISABLED', 'disabled_by' => $actor['user_id'], 'disabled_at' => now(),
        ]);
        $this->assignRole($actor['user_id'], 'hq_admin');
        $ranges = $this->app->make(ConsignmentNumberRangeService::class);
        $first = $ranges->create($principal, ['title' => 'First FIFO', 'numeric_prefix' => '888001', 'total_length' => 12, 'serial_start' => '1', 'serial_end' => '1'], (string) Str::uuid());
        $second = $ranges->create($principal, ['title' => 'Second FIFO', 'numeric_prefix' => '888002', 'total_length' => 12, 'serial_start' => '1', 'serial_end' => '2'], (string) Str::uuid());
        DB::table('consignment_number_ranges')->where('range_id', $first['range_id'])->update(['created_at' => now()->subMinute()]);
        $this->assignRole($actor['user_id'], 'branch_manager');

        $pricing = $this->app->make(PricingService::class);
        $service = $this->app->make(ConsignmentService::class);
        $draft = $this->draft();
        $quoteOne = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $one = $service->create($principal, $node, [...$draft, 'accepted_quote' => ['quote_id' => $quoteOne['quote_id'], 'quote_version' => 1, 'option_id' => $quoteOne['options'][0]['option_id']]], (string) Str::uuid());
        $quoteTwo = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $two = $service->create($principal, $node, [...$draft, 'accepted_quote' => ['quote_id' => $quoteTwo['quote_id'], 'quote_version' => 1, 'option_id' => $quoteTwo['options'][0]['option_id']]], (string) Str::uuid());

        $this->assertSame('888001000001', $one['consignment_number']);
        $this->assertSame('888002000001', $two['consignment_number']);
        $this->assertDatabaseHas('consignment_number_ranges', ['range_id' => $first['range_id'], 'status' => 'EXHAUSTED']);
        $this->assertDatabaseHas('consignment_number_ranges', ['range_id' => $second['range_id'], 'status' => 'AVAILABLE', 'next_serial' => '000002']);
    }

    public function test_create_rollback_does_not_consume_or_ledger_a_number(): void
    {
        [, , $node, $principal] = $this->branchContext('RANGE-ROLLBACK', 'range-rollback');
        $range = DB::table('consignment_number_ranges')->where('hq_id', $principal->hqId)->first();
        $draft = $this->draft();
        $pricing = $this->app->make(PricingService::class);
        $quote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $this->app->instance(OutboxWriter::class, new class implements OutboxWriter {
            public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void
            {
                throw new \RuntimeException('forced outbox rollback');
            }
        });
        try {
            $this->app->make(ConsignmentService::class)->create($principal, $node, [...$draft, 'accepted_quote' => ['quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id']]], (string) Str::uuid());
            $this->fail('The forced outbox failure must roll back Consignment creation.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('forced outbox rollback', $exception->getMessage());
        }
        $this->assertDatabaseCount('consignments', 0);
        $this->assertDatabaseCount('consignment_number_allocations', 0);
        $this->assertDatabaseHas('consignment_number_ranges', ['range_id' => $range->range_id, 'next_serial' => '000001', 'status' => 'AVAILABLE']);
    }

    public function test_historical_chb_consignment_numbers_remain_readable(): void
    {
        [, , $node, $principal] = $this->branchContext('RANGE-LEGACY', 'range-legacy');
        $draft = $this->draft();
        $pricing = $this->app->make(PricingService::class);
        $quote = $pricing->calculate($principal, $node, 'CREATE', $draft, null, null);
        $created = $this->app->make(ConsignmentService::class)->create($principal, $node, [...$draft, 'accepted_quote' => ['quote_id' => $quote['quote_id'], 'quote_version' => 1, 'option_id' => $quote['options'][0]['option_id']]], (string) Str::uuid());
        DB::table('consignments')->where('consignment_id', $created['consignment_id'])->update(['consignment_number' => 'CHB-2608-000001']);
        $legacy = $this->app->make(ConsignmentService::class)->get($principal, $node, $created['consignment_id']);
        $this->assertSame('CHB-2608-000001', $legacy['consignment_number']);
    }

    public function test_registry_and_range_rows_serialize_concurrent_writers(): void
    {
        [, , , $principal] = $this->branchContext('RANGE-LOCKS', 'range-locks');
        config(['database.connections.mysql_contender' => config('database.connections.mysql')]);
        $owner = DB::connection('mysql');
        $contender = DB::connection('mysql_contender');
        $contender->statement('SET SESSION innodb_lock_wait_timeout = 1');

        foreach ([
            fn () => $owner->table('consignment_number_range_registry')->where('registry_key', 'GLOBAL')->lockForUpdate()->first(),
            fn () => $owner->table('consignment_number_ranges')->where('hq_id', $principal->hqId)->lockForUpdate()->first(),
        ] as $index => $takeOwnerLock) {
            $owner->beginTransaction();
            $takeOwnerLock();
            $contender->beginTransaction();
            try {
                if ($index === 0) {
                    $contender->table('consignment_number_range_registry')->where('registry_key', 'GLOBAL')->lockForUpdate()->first();
                } else {
                    $contender->table('consignment_number_ranges')->where('hq_id', $principal->hqId)->lockForUpdate()->first();
                }
                $this->fail('A concurrent writer must wait for the authoritative lock.');
            } catch (\Illuminate\Database\QueryException $exception) {
                $this->assertStringContainsString('Lock wait timeout exceeded', $exception->getMessage());
            } finally {
                $contender->rollBack();
                $owner->rollBack();
            }
        }
        DB::purge('mysql_contender');
    }

    private function assignRole(string $userId, string $roleCode): void
    {
        $roleId = (string) DB::table('roles')->where('role_code', $roleCode)->value('role_id');
        DB::table('user_role_assignments')->where('user_id', $userId)->update([
            'role_id' => $roleId,
            'active_slot' => hash('sha256', "{$userId}|{$roleId}|TENANT|-"),
        ]);
        $this->app->make(\Modules\Authorization\Application\AuthorizationService::class)->invalidateUser($userId);
    }

    /** @return array{array<string, mixed>, array<string, mixed>, string, AuthenticatedPrincipal} */
    private function branchContext(string $code, string $username): array
    {
        $tenant = $this->tenant($code);
        $actor = $this->user($tenant['hq_id'], $username);
        foreach (['Foundation', 'Consignment', 'Parcel', 'Audit'] as $module) {
            DB::table('tenant_module_entitlements')->insert([
                'entitlement_id' => (string) Str::uuid(),
                'hq_id' => $tenant['hq_id'],
                'module_code' => $module,
                'status' => 'ENABLED',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $areaId = (string) Str::uuid();
        DB::table('areas')->insert([
            'area_id' => $areaId,
            'hq_id' => $tenant['hq_id'],
            'area_title' => 'Branch area',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $nodeId = (string) Str::uuid();
        DB::table('nodes')->insert([
            'node_id' => $nodeId,
            'hq_id' => $tenant['hq_id'],
            'area_id' => $areaId,
            'node_code' => $code,
            'node_title' => 'Branch node',
            'node_type' => 'BRANCH',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = (string) DB::table('roles')->where('role_code', 'branch_manager')->value('role_id');
        DB::table('user_role_assignments')->insert([
            'assignment_id' => (string) Str::uuid(),
            'hq_id' => $tenant['hq_id'],
            'user_id' => $actor['user_id'],
            'role_id' => $roleId,
            'scope_type' => 'TENANT',
            'scope_id' => null,
            'includes_descendants' => false,
            'status' => 'ACTIVE',
            'active_slot' => hash('sha256', "{$actor['user_id']}|{$roleId}|TENANT|-"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $prefix = (string) ++self::$rangeSequence;
        DB::table('consignment_number_ranges')->insert([
            'range_id' => (string) Str::uuid(),
            'hq_id' => $tenant['hq_id'],
            'title' => 'Integration allocation inventory',
            'numeric_prefix' => $prefix,
            'total_length' => 12,
            'serial_width' => 6,
            'serial_start' => '000001',
            'serial_end' => '999999',
            'next_serial' => '000001',
            'first_number' => $prefix.'000001',
            'last_number' => $prefix.'999999',
            'status' => 'AVAILABLE',
            'created_by' => $actor['user_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            $tenant,
            $actor,
            $nodeId,
            new AuthenticatedPrincipal($actor['user_id'], (string) Str::uuid(), $tenant['hq_id'], false),
        ];
    }

    /** @return array<string, mixed> */
    private function draft(): array
    {
        return [
            'sender' => [
                'contact_name' => 'Sender',
                'mobile' => '09120000001',
                'address_text' => 'Sender address',
                'city_id' => GeographyIds::city('10866'),
                'state' => 'Tehran',
                'city' => 'Tehran',
            ],
            'receiver' => [
                'contact_name' => 'Receiver',
                'mobile' => '09120000002',
                'address_text' => 'Receiver address',
                'city_id' => GeographyIds::city('10866'),
                'state' => 'Tehran',
                'city' => 'Tehran',
            ],
            'service_type_id' => (string) Str::uuid(),
            'shipping_method_id' => (string) Str::uuid(),
            'pickup_commitment_at' => '2026-08-01T11:00:00Z',
            'delivery_commitment_at' => null,
            'weight_kg' => 2,
            'width_cm' => 10,
            'length_cm' => 20,
            'height_cm' => 30,
            'declared_value_amount' => 2000000,
            'insurance_enabled' => true,
            'insurance_value_amount' => 2000000,
            'cod_enabled' => false,
            'cod_amount' => null,
            'payer' => 'SENDER',
            'payment_method' => 'CASH',
            'parcels' => [
                ['content_description' => 'اسناد', 'weight_kg' => 1, 'width_cm' => 10, 'length_cm' => 20, 'height_cm' => 30],
                ['content_description' => null, 'weight_kg' => 1, 'width_cm' => 10, 'length_cm' => 20, 'height_cm' => 30],
            ],
        ];
    }
}
