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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The contract file of a customer: what was signed, its validity window and amount, and the archive
 * documents attached to it through the document links.
 */
final class CustomerContractTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_catalog_categories', 'crm_catalog_personas',
        'crm_catalog_sales_models', 'crm_catalog_items', 'crm_sales_funnel', 'crm_sales_funnel_steps',
        'crm_opportunities', 'crm_sales_documents', 'crm_sales_document_versions', 'crm_contracts',
        'document_categories', 'documents', 'document_links',
    ];

    public function test_a_contract_is_recorded_as_a_draft_unless_a_status_is_named(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), ['reference_no' => ' CN-1405-01 '])
            ->assertCreated()
            ->assertJsonPath('data.reference_no', 'CN-1405-01')
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.amount', null)
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.documents', [])
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.hq_id');

        $this->assertDatabaseHas('crm_contracts', ['hq_id' => 1, 'customer_id' => 11, 'reference_no' => 'CN-1405-01', 'status' => 'DRAFT', 'created_by' => 1]);
    }

    public function test_a_contract_keeps_its_window_amount_commitments_and_references(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.start_date', '2026-10-01')
            ->assertJsonPath('data.end_date', '2027-09-30')
            ->assertJsonPath('data.amount', 900000000)
            ->assertJsonPath('data.commitments', 'پشتیبانی ۲۴ ساعته')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.opportunity_id', '3')
            ->assertJsonPath('data.proforma_version_id', '1');
    }

    public function test_the_list_is_newest_first_and_carries_the_archive_documents_in_one_read(): void
    {
        $this->authenticate();
        $this->contract(['id' => 1, 'reference_no' => 'CN-1', 'created_at' => '2026-09-01 10:00:00']);
        $this->contract(['id' => 2, 'reference_no' => 'CN-2', 'created_at' => '2026-09-02 10:00:00']);
        $this->contract(['id' => 3, 'reference_no' => 'CN-3', 'created_at' => '2026-09-02 10:00:00']);
        // A contract of another customer of the same tenant stays out of the list.
        $this->contract(['id' => 4, 'customer_id' => 13, 'reference_no' => 'CN-4']);
        DB::table('documents')->insert([
            ['id' => 1, 'hq_id' => 1, 'title' => 'متن قرارداد', 'classification' => 'عادی', 'status' => 'ACTIVE', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'title' => 'ضمیمه فنی', 'classification' => 'عادی', 'status' => 'ACTIVE', 'created_by' => 1],
        ]);
        DB::table('document_links')->insert([
            ['id' => 1, 'hq_id' => 1, 'document_id' => 1, 'resource_type' => 'CONTRACT', 'resource_id' => 1, 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'document_id' => 2, 'resource_type' => 'CONTRACT', 'resource_id' => 1, 'created_by' => 1],
            // The same numeric ID under another kind of record is not a contract file.
            ['id' => 3, 'hq_id' => 1, 'document_id' => 2, 'resource_type' => 'CUSTOMER', 'resource_id' => 2, 'created_by' => 1],
        ]);

        DB::enableQueryLog();
        $response = $this->getJson($this->endpoint())->assertOk();
        $linkReads = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'document_links'));

        self::assertSame(['CN-3', 'CN-2', 'CN-1'], array_column($response->json('data'), 'reference_no'));
        self::assertSame([], $response->json('data.0.documents'));
        self::assertSame([
            ['document_id' => '1', 'title' => 'متن قرارداد'],
            ['document_id' => '2', 'title' => 'ضمیمه فنی'],
        ], $response->json('data.2.documents'));
        // However many contracts are listed, the links are read once for all of them.
        self::assertCount(1, $linkReads);
    }

    public function test_a_document_attached_through_the_archive_appears_on_the_contract(): void
    {
        $this->authenticate(['crm.contract.view', 'crm.contract.manage', 'crm.document.view', 'crm.document.manage']);
        $contractId = $this->postJson($this->endpoint(), ['reference_no' => 'CN-9'])->assertCreated()->json('data.contract_id');
        DB::table('documents')->insert(['id' => 1, 'hq_id' => 1, 'title' => 'متن قرارداد', 'classification' => 'عادی', 'status' => 'ACTIVE', 'created_by' => 1]);

        $this->postJson('/api/v1/crm/documents/1/links', ['resource_type' => 'CONTRACT', 'resource_id' => (int) $contractId, 'purpose' => 'متن'])
            ->assertCreated();
        // A contract that does not exist cannot be a target of a link.
        $this->postJson('/api/v1/crm/documents/1/links', ['resource_type' => 'CONTRACT', 'resource_id' => 999])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.resource_id', ['Select an existing record of this tenant to attach the document to.']);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.0.documents', [['document_id' => '1', 'title' => 'متن قرارداد']]);
    }

    public function test_the_end_cannot_fall_before_the_start(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), $this->payload(['start_date' => '2026-10-01', 'end_date' => '2026-09-30']))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.end_date', ['The end date cannot fall before the start date.']);
        // A single day is a valid window.
        $this->postJson($this->endpoint(), $this->payload(['reference_no' => 'CN-2', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01']))
            ->assertCreated();
        $this->assertDatabaseCount('crm_contracts', 1);
    }

    public function test_only_a_customer_is_offered_a_contract_and_a_stranger_is_not_found(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint('12'), ['reference_no' => 'CN-1'])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.customer_id', ['A contract is signed only with a record that has been promoted to a customer.']);
        $this->postJson($this->endpoint('99'), ['reference_no' => 'CN-1'])->assertNotFound();
        $this->getJson($this->endpoint('99'))->assertNotFound();
        $this->getJson($this->endpoint('500'))->assertNotFound();
        $this->assertDatabaseCount('crm_contracts', 0);
    }

    #[DataProvider('unusableReferences')]
    public function test_references_to_records_of_another_customer_or_tenant_are_refused(array $changes, string $field): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), $this->payload($changes))
            ->assertStatus(422)
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_contracts', 0);
    }

    public static function unusableReferences(): array
    {
        return [
            'opportunity of another customer' => [['opportunity_id' => 4], 'opportunity_id'],
            'opportunity of another tenant' => [['opportunity_id' => 9], 'opportunity_id'],
            'opportunity that does not exist' => [['opportunity_id' => 404], 'opportunity_id'],
            'proforma of another customer' => [['proforma_version_id' => 2], 'proforma_version_id'],
            'proforma of another tenant' => [['proforma_version_id' => 3], 'proforma_version_id'],
            'proforma that does not exist' => [['proforma_version_id' => 404], 'proforma_version_id'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_malformed_bodies_are_refused(array $changes, string $field): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), array_replace($this->payload(), $changes))
            ->assertStatus(422)
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_contracts', 0);
    }

    public static function invalidBodies(): array
    {
        return [
            'no reference' => [['reference_no' => ''], 'reference_no'],
            'reference too long' => [['reference_no' => str_repeat('a', 121)], 'reference_no'],
            'lower-case status' => [['status' => 'active'], 'status'],
            'status too long' => [['status' => 'A'.str_repeat('B', 40)], 'status'],
            'negative amount' => [['amount' => -1], 'amount'],
            'fractional amount' => [['amount' => 10.5], 'amount'],
            'date in another format' => [['start_date' => '01/10/2026'], 'start_date'],
            'impossible date' => [['end_date' => '2026-13-40'], 'end_date'],
        ];
    }

    public function test_the_same_reference_twice_for_one_customer_conflicts_but_not_across_customers(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), ['reference_no' => 'CN-1'])->assertCreated();
        $this->postJson($this->endpoint(), ['reference_no' => 'CN-1'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONTRACT_REFERENCE_EXISTS');
        $this->postJson($this->endpoint('13'), ['reference_no' => 'CN-1'])->assertCreated();
        $this->assertDatabaseCount('crm_contracts', 2);
    }

    public function test_the_contract_permissions_are_separate_for_reading_and_writing(): void
    {
        $this->authenticate(['crm.contract.view']);
        $this->getJson($this->endpoint())->assertOk();
        $this->postJson($this->endpoint(), ['reference_no' => 'CN-1'])->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->assertDatabaseCount('crm_contracts', 0);
    }

    public function test_reading_needs_the_view_permission(): void
    {
        $this->authenticate(['crm.contract.manage']);

        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
    }

    #[DataProvider('deniedContexts')]
    public function test_a_context_without_tenant_wide_module_access_is_refused(?string $hqId, bool $enabled, ScopeType $scope, string $error): void
    {
        $this->authenticate(hqId: $hqId, enabled: $enabled, scope: $scope);

        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', $error)->assertJsonMissingPath('data');
        $this->postJson($this->endpoint(), ['reference_no' => 'CN-1'])->assertForbidden()->assertJsonPath('error_code', $error);
        $this->assertDatabaseCount('crm_contracts', 0);
    }

    public static function deniedContexts(): array
    {
        return [
            'no tenant' => [null, true, ScopeType::TENANT, 'TENANT_ACCESS_DENIED'],
            'disabled module' => ['1', false, ScopeType::TENANT, 'ENTITLEMENT_DISABLED'],
            'node scope' => ['1', true, ScopeType::NODE, 'SCOPE_ACCESS_DENIED'],
            'self scope' => ['1', true, ScopeType::SelfScope, 'SCOPE_ACCESS_DENIED'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true]);
        DB::purge('sqlite');
        foreach (self::TABLES as $table) {
            (require glob(base_path('Modules/*/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        // The circular document/version foreign key is added by ALTER TABLE, which SQLite cannot do; it
        // is a MySQL-side guarantee and nothing here depends on it being declared.
        (require base_path('Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php'))->up();
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CRM-SALES', 'hq_title' => 'Sales tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Seller', 'display_name' => 'Test Seller', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Seller', 'display_name' => 'Other Seller', 'status' => 'ACTIVE'],
        ]);
        DB::table('crm_customers')->insert([
            ['id' => 11, 'hq_id' => 1, 'created_by' => 1, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER', 'display_name' => 'پارس‌گستر آریا', 'customer_code' => 'C-1001'],
            ['id' => 12, 'hq_id' => 1, 'created_by' => 1, 'kind' => 'COMPANY', 'phase' => 'LEAD', 'display_name' => 'سرنخ نمونه'],
            ['id' => 13, 'hq_id' => 1, 'created_by' => 1, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER', 'display_name' => 'مشتری دوم'],
            ['id' => 99, 'hq_id' => 2, 'created_by' => 2, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER', 'display_name' => 'مشتری سازمان دیگر'],
        ]);
        DB::table('crm_sales_funnel')->insert([
            ['id' => 1, 'hq_id' => 1, 'code' => 'DEFAULT', 'title' => 'قیف فروش', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 2, 'code' => 'DEFAULT', 'title' => 'قیف فروش', 'created_by' => 2],
        ]);
        DB::table('crm_sales_funnel_steps')->insert([
            ['id' => 1, 'hq_id' => 1, 'funnel_id' => 1, 'code' => 'NEGOTIATION', 'title' => 'مذاکره', 'sort_order' => 1, 'outcome_type' => 'OPEN', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 2, 'funnel_id' => 2, 'code' => 'NEGOTIATION', 'title' => 'مذاکره', 'sort_order' => 1, 'outcome_type' => 'OPEN', 'created_by' => 2],
        ]);
        DB::table('crm_opportunities')->insert([
            ['id' => 3, 'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 1, 'assignee_id' => 1, 'created_by' => 1, 'title' => 'قرارداد سالانه'],
            ['id' => 4, 'hq_id' => 1, 'customer_id' => 13, 'funnel_id' => 1, 'current_step_id' => 1, 'assignee_id' => 1, 'created_by' => 1, 'title' => 'فرصت مشتری دوم'],
            ['id' => 9, 'hq_id' => 2, 'customer_id' => 99, 'funnel_id' => 2, 'current_step_id' => 2, 'assignee_id' => 2, 'created_by' => 2, 'title' => 'فرصت سازمان دیگر'],
        ]);
        DB::table('crm_sales_documents')->insert([
            ['id' => 1, 'hq_id' => 1, 'opportunity_id' => 3, 'customer_id' => 11, 'document_no' => 'PR-2026-0001', 'document_type' => 'PROFORMA', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'opportunity_id' => 4, 'customer_id' => 13, 'document_no' => 'PR-2026-0002', 'document_type' => 'PROFORMA', 'created_by' => 1],
            ['id' => 3, 'hq_id' => 2, 'opportunity_id' => 9, 'customer_id' => 99, 'document_no' => 'PR-2026-0001', 'document_type' => 'PROFORMA', 'created_by' => 2],
        ]);
        DB::table('crm_sales_document_versions')->insert([
            ['id' => 1, 'hq_id' => 1, 'document_id' => 1, 'version_no' => 1, 'status' => 'DRAFT', 'currency' => 'IRR', 'total' => 900000000, 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'document_id' => 2, 'version_no' => 1, 'status' => 'DRAFT', 'currency' => 'IRR', 'total' => 500000000, 'created_by' => 1],
            ['id' => 3, 'hq_id' => 2, 'document_id' => 3, 'version_no' => 1, 'status' => 'DRAFT', 'currency' => 'IRR', 'total' => 100000000, 'created_by' => 2],
        ]);
        // English field errors, so the assertions above read as lang/en/api.php writes them.
        $this->withHeader('Accept-Language', 'en');
    }

    private function endpoint(string $customerId = '11'): string
    {
        return '/api/v1/crm/customers/'.$customerId.'/contracts';
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'reference_no' => 'CN-1405-01',
            'opportunity_id' => 3,
            'proforma_version_id' => 1,
            'start_date' => '2026-10-01',
            'end_date' => '2027-09-30',
            'amount' => 900000000,
            'commitments' => 'پشتیبانی ۲۴ ساعته',
            'status' => 'ACTIVE',
        ], $changes);
    }

    private function contract(array $attributes = []): void
    {
        DB::table('crm_contracts')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'reference_no' => 'CN-'.($attributes['id'] ?? 1),
            'status' => 'DRAFT', 'created_by' => 1, 'created_at' => '2026-09-01 10:00:00',
        ], $attributes));
    }

    /** @param list<string> $permissions */
    private function authenticate(
        array $permissions = ['crm.contract.view', 'crm.contract.manage'],
        ?string $hqId = '1',
        bool $enabled = true,
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'crm-contract-session', $hqId, false, 'crm-contract-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('crm-contract')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $scopes = [];
        foreach ($permissions as $permission) {
            $scopes[$permission] = [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')];
        }
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: $scopes,
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('crm-contract');

        return $principal;
    }
}
