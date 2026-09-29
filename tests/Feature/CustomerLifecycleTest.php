<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\AccessTokenClaims;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Tests\TestCase;

final class CustomerLifecycleTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_catalog_categories', 'crm_catalog_personas', 'crm_catalog_sales_models',
        'crm_catalog_items', 'crm_sales_funnel', 'crm_sales_funnel_steps', 'crm_opportunities', 'crm_tasks',
        'crm_task_assignment_events', 'crm_activities',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    public function test_closing_a_lead_records_the_reason_as_a_note(): void
    {
        $this->authenticate();

        $response = $this->postJson($this->endpoint(), ['lifecycle' => 'INACTIVE', 'reason' => '  Need dropped  '])->assertOk()
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.lifecycle', 'INACTIVE');

        $activityId = $response->json('data.activity_id');
        self::assertNotNull($activityId);
        $this->assertDatabaseHas('crm_activities', [
            'id' => $activityId, 'hq_id' => 1, 'type' => 'NOTE', 'customer_id' => 11, 'body' => 'Need dropped', 'created_by' => 1,
        ]);
        $this->assertDatabaseHas('crm_customers', ['id' => 11, 'lifecycle' => 'INACTIVE']);
    }

    public function test_a_record_of_any_phase_can_be_archived_and_reopened(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'phase' => 'CUSTOMER']);

        $this->postJson($this->endpoint('12'), ['lifecycle' => 'ARCHIVED', 'reason' => 'Contract ended'])->assertOk();
        $this->postJson($this->endpoint('12'), ['lifecycle' => 'ACTIVE'])->assertOk()->assertJsonPath('data.activity_id', null);
        $this->postJson($this->endpoint('12'), ['lifecycle' => 'INACTIVE', 'reason' => 'Paused'])->assertOk();
        $this->postJson($this->endpoint('12'), ['lifecycle' => 'ACTIVE', 'reason' => 'Back again'])->assertOk()
            ->assertJsonPath('data.lifecycle', 'ACTIVE')->assertJsonPath('data.activity_id', '3');

        $this->assertDatabaseCount('crm_activities', 3);
    }

    public function test_leaving_the_active_state_needs_a_reason(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), ['lifecycle' => 'INACTIVE'])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['reason']]);
        $this->postJson($this->endpoint(), ['lifecycle' => 'ARCHIVED', 'reason' => '   '])->assertUnprocessable();
        $this->postJson($this->endpoint(), ['lifecycle' => 'ARCHIVED', 'reason' => str_repeat('x', 1001)])->assertUnprocessable();
        $this->postJson($this->endpoint(), ['lifecycle' => 'BOGUS', 'reason' => 'x'])->assertUnprocessable();
        $this->assertDatabaseHas('crm_customers', ['id' => 11, 'lifecycle' => 'ACTIVE']);
        $this->assertDatabaseCount('crm_activities', 0);
    }

    public function test_an_unchanged_lifecycle_is_refused_without_a_second_note(): void
    {
        $this->authenticate();
        $this->postJson($this->endpoint(), ['lifecycle' => 'INACTIVE', 'reason' => 'first'])->assertOk();

        $this->postJson($this->endpoint(), ['lifecycle' => 'INACTIVE', 'reason' => 'again'])->assertUnprocessable()
            ->assertJsonStructure(['field_errors' => ['lifecycle']]);
        $this->assertDatabaseCount('crm_activities', 1);
    }

    public function test_foreign_and_unknown_records_are_missing(): void
    {
        $this->authenticate();
        $this->customer(['id' => 21, 'hq_id' => 2, 'created_by' => 2]);

        $this->postJson($this->endpoint('21'), ['lifecycle' => 'INACTIVE', 'reason' => 'x'])->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->postJson($this->endpoint('999'), ['lifecycle' => 'INACTIVE', 'reason' => 'x'])->assertNotFound();
        $this->assertDatabaseCount('crm_activities', 0);
    }

    public function test_the_change_needs_edit_and_an_enabled_module(): void
    {
        $this->authenticate(permissions: ['customer.view']);
        $this->postJson($this->endpoint(), ['lifecycle' => 'INACTIVE', 'reason' => 'x'])->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');

        $this->authenticate(enabled: false);
        $this->postJson($this->endpoint(), ['lifecycle' => 'INACTIVE', 'reason' => 'x'])->assertForbidden()->assertJsonPath('error_code', 'ENTITLEMENT_DISABLED');
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true]);
        DB::purge('sqlite');
        foreach (self::TABLES as $table) {
            (require glob(base_path('Modules/*/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require base_path(self::NULLABLE_MIGRATION))->up();
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'customer-lifecycle', 'hq_title' => 'customer-lifecycle tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer();
        // English field errors, so the assertions below read as lang/en/api.php writes them.
        $this->withHeader('Accept-Language', 'en');
    }

    private function customer(array $attributes = []): void
    {
        DB::table('crm_customers')->insert(array_replace([
            'id' => 11, 'hq_id' => 1, 'created_by' => 1, 'assignee_id' => null, 'kind' => 'PERSON',
            'phase' => 'LEAD', 'lifecycle' => 'ACTIVE', 'first_name' => 'Nima', 'family_name' => 'Sample',
            'display_name' => 'Nima Sample', 'created_at' => '2026-09-28 10:00:00', 'updated_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function endpoint(string $customerId = '11'): string
    {
        return '/api/v1/crm/customers/'.$customerId.'/lifecycle';
    }


    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit'],
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-lifecycle-session', $hqId, false, 'customer-lifecycle-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-lifecycle')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope(ScopeType::TENANT, null)]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-lifecycle');

        return $principal;
    }
}
