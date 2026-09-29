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

final class LeadConversionTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_catalog_categories', 'crm_catalog_personas', 'crm_catalog_sales_models',
        'crm_catalog_items', 'crm_sales_funnel', 'crm_sales_funnel_steps', 'crm_opportunities', 'crm_tasks',
        'crm_task_assignment_events', 'crm_activities',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    public function test_a_lead_becomes_a_customer_with_the_same_identity(): void
    {
        $this->authenticate();

        $response = $this->postJson($this->endpoint(), ['customer_code' => 'C-1042'])->assertOk()
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.phase', 'CUSTOMER')
            ->assertJsonPath('data.customer_code', 'C-1042')
            ->assertJsonPath('data.merged_into', null);

        self::assertNotNull($response->json('data.converted_at'));
        $row = DB::table('crm_customers')->where('id', 11)->first();
        self::assertSame('CUSTOMER', $row->phase);
        self::assertNotNull($row->converted_at);
        self::assertSame('Nima Sample', $row->display_name);
        self::assertNotSame('2026-09-28 10:00:00', $row->updated_at);
    }

    public function test_supplied_names_replace_the_stored_ones_and_rebuild_the_display_name(): void
    {
        $this->authenticate();
        $this->customer(['id' => 14, 'first_name' => null, 'family_name' => null, 'display_name' => null]);
        $this->customer(['id' => 16, 'display_name' => 'Custom name']);

        $this->postJson($this->endpoint('14'), ['first_name' => 'Ali', 'family_name' => 'Rezaei'])->assertOk();
        $this->assertDatabaseHas('crm_customers', ['id' => 14, 'first_name' => 'Ali', 'family_name' => 'Rezaei', 'display_name' => 'Ali Rezaei']);

        $this->postJson($this->endpoint('16'), ['first_name' => 'New', 'display_name' => 'Chosen name'])->assertOk();
        $this->assertDatabaseHas('crm_customers', ['id' => 16, 'first_name' => 'New', 'display_name' => 'Chosen name']);
    }

    public function test_a_person_without_names_cannot_be_converted_and_a_company_only_needs_a_display_name(): void
    {
        $this->authenticate();
        $this->customer(['id' => 14, 'first_name' => null, 'family_name' => null, 'display_name' => null]);
        $this->customer(['id' => 15, 'kind' => 'COMPANY', 'first_name' => null, 'family_name' => null, 'display_name' => 'Acme']);

        $this->postJson($this->endpoint('14'))->assertUnprocessable()
            ->assertJsonStructure(['field_errors' => ['first_name', 'family_name', 'display_name']]);
        $this->assertDatabaseHas('crm_customers', ['id' => 14, 'phase' => 'LEAD']);
        $this->postJson($this->endpoint('15'))->assertOk();
    }

    public function test_only_an_active_lead_can_be_converted(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'phase' => 'CUSTOMER']);
        $this->customer(['id' => 13, 'lifecycle' => 'INACTIVE']);

        $this->postJson($this->endpoint('12'))->assertStatus(409)->assertJsonPath('error_code', 'CUSTOMER_ALREADY_CONVERTED');
        $this->postJson($this->endpoint('13'))->assertUnprocessable()->assertJsonStructure(['field_errors' => ['customer_id']]);
        $this->postJson($this->endpoint())->assertOk();
        $this->postJson($this->endpoint())->assertStatus(409)->assertJsonPath('error_code', 'CUSTOMER_ALREADY_CONVERTED');
    }

    public function test_a_code_of_another_customer_is_a_conflict_and_nothing_changes(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'phase' => 'CUSTOMER', 'customer_code' => 'C-1']);

        $this->postJson($this->endpoint(), ['customer_code' => 'C-1'])->assertStatus(409)->assertJsonPath('error_code', 'CUSTOMER_CODE_EXISTS');
        $this->assertDatabaseHas('crm_customers', ['id' => 11, 'phase' => 'LEAD']);
        $this->postJson($this->endpoint(), ['customer_code' => str_repeat('a', 81)])->assertUnprocessable();
    }

    public function test_an_empty_code_keeps_the_stored_one_and_the_first_promotion_date_survives(): void
    {
        $this->authenticate();
        $this->customer(['id' => 17, 'customer_code' => 'OLD', 'converted_at' => '2026-01-01 00:00:00']);

        $this->postJson($this->endpoint('17'), ['customer_code' => ''])->assertOk()->assertJsonPath('data.customer_code', 'OLD');
        self::assertStringStartsWith('2026-01-01', (string) DB::table('crm_customers')->where('id', 17)->value('converted_at'));
    }

    public function test_merging_into_an_existing_customer_is_not_available_yet(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'phase' => 'CUSTOMER']);

        $this->postJson($this->endpoint(), ['merge_into_customer_id' => 12])->assertUnprocessable()
            ->assertJsonStructure(['field_errors' => ['merge_into_customer_id']]);
        $this->postJson($this->endpoint(), ['confirm_merge' => true])->assertUnprocessable()
            ->assertJsonStructure(['field_errors' => ['confirm_merge']]);
        $this->assertDatabaseHas('crm_customers', ['id' => 11, 'phase' => 'LEAD']);
    }

    public function test_foreign_and_unknown_records_are_missing(): void
    {
        $this->authenticate();
        $this->customer(['id' => 21, 'hq_id' => 2, 'created_by' => 2]);

        $this->postJson($this->endpoint('21'))->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->postJson($this->endpoint('999'))->assertNotFound();
    }

    public function test_the_conversion_needs_edit_and_an_enabled_module(): void
    {
        $this->authenticate(permissions: ['customer.view']);
        $this->postJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');

        $this->authenticate(enabled: false);
        $this->postJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', 'ENTITLEMENT_DISABLED');
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
            ['id' => 1, 'hq_code' => 'lead-conversion', 'hq_title' => 'lead-conversion tenant'],
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
        return '/api/v1/crm/customers/'.$customerId.'/convert';
    }


    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit'],
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'lead-conversion-session', $hqId, false, 'lead-conversion-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('lead-conversion')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope(ScopeType::TENANT, null)]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('lead-conversion');

        return $principal;
    }
}
