<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Consignment\Application\ConsignmentService;
use Modules\Consignment\Application\Contracts\PricingQuoteProvider;
use Modules\Consignment\Application\PricingService;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final class ConsignmentIntegrationTest extends MySqlRedisTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
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
        $this->assertMatchesRegularExpression('/^CHB-\d{4}-\d{6}$/', $created['consignment_number']);
        $this->assertSame("{$created['consignment_number']}-01", $created['parcels'][0]['parcel_number']);
        $this->assertSame(2500, $created['accepted_pricing_versions'][0]['total_amount']);
        $this->assertDatabaseCount('consignments', 1);
        $this->assertDatabaseCount('parcels', 2);
        $this->assertDatabaseCount('consignment_pricing_versions', 1);
        $this->assertDatabaseCount('consignment_pricing_charge_lines', 2);
        $this->assertDatabaseCount('consignment_status_events', 3);
        $this->assertDatabaseHas('audit_events', ['action_key' => 'CONSIGNMENT_CREATED']);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'consignment.created']);

        $page = $this->app->make(ConsignmentService::class)->list(
            $principal,
            $node,
            ['page' => 1, 'page_size' => 25, 'search' => $created['parcels'][1]['parcel_number']],
        );
        $this->assertSame(1, $page->total());

        $editedDraft = $draft;
        $editedDraft['receiver']['address_text'] = 'Updated safe address';
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
                'state' => 'Tehran',
                'city' => 'Tehran',
            ],
            'receiver' => [
                'contact_name' => 'Receiver',
                'mobile' => '09120000002',
                'address_text' => 'Receiver address',
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
            'insurance_enabled' => false,
            'insurance_value_amount' => null,
            'cod_enabled' => false,
            'cod_amount' => null,
            'payer' => 'SENDER',
            'payment_method' => 'CASH',
            'parcels' => [
                ['weight_kg' => 1, 'width_cm' => 10, 'length_cm' => 20, 'height_cm' => 30],
                ['weight_kg' => 1, 'width_cm' => 10, 'length_cm' => 20, 'height_cm' => 30],
            ],
        ];
    }
}
