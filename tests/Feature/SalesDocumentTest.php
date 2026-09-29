<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
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

final class SalesDocumentTest extends TestCase
{
    private const ENDPOINT = '/api/v1/crm/sales-documents';

    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_catalog_categories', 'crm_catalog_personas',
        'crm_catalog_sales_models', 'crm_catalog_items', 'crm_sales_funnel', 'crm_sales_funnel_steps',
        'crm_opportunities', 'crm_sales_documents', 'crm_sales_document_versions',
    ];

    public function test_a_document_opens_on_a_numbered_draft_against_the_customer_of_its_opportunity(): void
    {
        $this->authenticate();

        $response = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()
            ->assertJsonPath('data.document_no', 'PR-'.date('Y').'-0001')
            ->assertJsonPath('data.document_type', 'PROPOSAL')
            ->assertJsonPath('data.customer.customer_id', '11')
            ->assertJsonPath('data.opportunity.opportunity_id', '3')
            ->assertJsonPath('data.current_version.version_no', 1)
            ->assertJsonPath('data.current_version.status', 'DRAFT')
            ->assertJsonPath('data.current_version.currency', 'IRR')
            ->assertJsonPath('data.current_version.total', 180000000)
            ->assertJsonPath('data.current_version.previous_version_id', null)
            ->assertJsonPath('data.current_version.issued_at', null)
            ->assertJsonPath('data.current_version.content_hash', null)
            ->assertJsonPath('data.current_version.customer_snapshot', null)
            ->assertJsonCount(1, 'data.versions');

        $this->assertDatabaseHas('crm_sales_documents', [
            'id' => $response->json('data.sales_document_id'), 'hq_id' => 1, 'created_by' => 1,
            'customer_id' => 11, 'opportunity_id' => 3, 'document_type' => 'PROPOSAL',
            'current_version_id' => $response->json('data.current_version.sales_document_version_id'),
        ]);
        $this->assertDatabaseHas('crm_sales_document_versions', [
            'id' => $response->json('data.current_version.sales_document_version_id'),
            'hq_id' => 1, 'created_by' => 1, 'version_no' => 1, 'status' => 'DRAFT', 'total' => 180000000,
        ]);
    }

    public function test_an_operator_may_name_the_document_and_the_name_is_unique_per_tenant(): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload(['document_no' => 'PF-1405-0012']))->assertCreated()
            ->assertJsonPath('data.document_no', 'PF-1405-0012');

        $this->postJson(self::ENDPOINT, $this->payload(['document_no' => 'PF-1405-0012']))
            ->assertConflict()->assertJsonPath('error_code', 'CONFLICT');
        $this->assertDatabaseCount('crm_sales_documents', 1);
    }

    public function test_the_counter_runs_per_document_type(): void
    {
        $this->authenticate();
        $year = date('Y');

        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()->assertJsonPath('data.document_no', 'PR-'.$year.'-0001');
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()->assertJsonPath('data.document_no', 'PR-'.$year.'-0002');
        $this->postJson(self::ENDPOINT, $this->payload(['document_type' => 'PROFORMA']))->assertCreated()
            ->assertJsonPath('data.document_no', 'PF-'.$year.'-0001');
        $this->postJson(self::ENDPOINT, $this->payload(['document_type' => 'ESTIMATE']))->assertCreated()
            ->assertJsonPath('data.document_no', 'ES-'.$year.'-0001');
    }

    #[DataProvider('rejectedDrafts')]
    public function test_a_draft_that_cannot_stand_is_refused_without_writing_anything(array $changes, string $field): void
    {
        $this->authenticate();

        $this->postJson(self::ENDPOINT, $this->payload($changes))->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_sales_documents', 0);
        $this->assertDatabaseCount('crm_sales_document_versions', 0);
    }

    public static function rejectedDrafts(): array
    {
        return [
            'opportunity of a lead' => [['opportunity_id' => 4], 'opportunity_id'],
            'opportunity of another tenant' => [['opportunity_id' => 9], 'opportunity_id'],
            'unknown opportunity' => [['opportunity_id' => 404], 'opportunity_id'],
            'missing opportunity' => [['opportunity_id' => null], 'opportunity_id'],
            'unknown document type' => [['document_type' => 'INVOICE'], 'document_type'],
            'long document number' => [['document_no' => str_repeat('P', 81)], 'document_no'],
            'validity in the past' => [['version' => ['expires_at' => '2020-01-01T00:00:00+00:00']], 'expires_at'],
            'negative total' => [['version' => ['total' => -1]], 'version.total'],
            'fractional total' => [['version' => ['total' => 1.5]], 'version.total'],
            'missing total' => [['version' => ['total' => null]], 'version.total'],
            'lowercase currency is accepted but a long one is not' => [['version' => ['currency' => 'RIALS']], 'version.currency'],
            'long terms' => [['version' => ['terms' => str_repeat('ا', 5001)]], 'version.terms'],
        ];
    }

    public function test_issuing_freezes_the_customer_and_fingerprints_the_content(): void
    {
        $this->authenticate();
        $created = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $versionId = $created->json('data.current_version.sales_document_version_id');

        $issued = $this->postJson($this->versionEndpoint($versionId, 'issue'))->assertOk()
            ->assertJsonPath('data.current_version.status', 'ISSUED')
            ->assertJsonPath('data.current_version.customer_snapshot.customer_id', '11')
            ->assertJsonPath('data.current_version.customer_snapshot.display_name', 'پارس‌گستر آریا')
            ->assertJsonPath('data.current_version.customer_snapshot.customer_code', 'C-1001');

        self::assertNotNull($issued->json('data.current_version.issued_at'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $issued->json('data.current_version.content_hash'));
        $this->assertDatabaseHas('crm_sales_document_versions', ['id' => $versionId, 'status' => 'ISSUED']);

        // A frozen revision does not move twice.
        $this->postJson($this->versionEndpoint($versionId, 'issue'))->assertConflict()->assertJsonPath('error_code', 'CONFLICT');
    }

    public function test_acceptance_follows_issuing_and_never_precedes_it(): void
    {
        $this->authenticate();
        $versionId = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()
            ->json('data.current_version.sales_document_version_id');

        $this->postJson($this->versionEndpoint($versionId, 'accept'))->assertConflict();
        $this->postJson($this->versionEndpoint($versionId, 'issue'))->assertOk();
        $this->postJson($this->versionEndpoint($versionId, 'accept'))->assertOk()
            ->assertJsonPath('data.current_version.status', 'ACCEPTED');
        $this->postJson($this->versionEndpoint($versionId, 'accept'))->assertConflict();
    }

    #[DataProvider('cancellableStatuses')]
    public function test_a_draft_or_an_issued_revision_can_be_cancelled(bool $issueFirst): void
    {
        $this->authenticate();
        $versionId = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()
            ->json('data.current_version.sales_document_version_id');
        if ($issueFirst) {
            $this->postJson($this->versionEndpoint($versionId, 'issue'))->assertOk();
        }

        $this->postJson($this->versionEndpoint($versionId, 'cancel'))->assertOk()
            ->assertJsonPath('data.current_version.status', 'CANCELLED');
        $this->postJson($this->versionEndpoint($versionId, 'cancel'))->assertConflict();
    }

    public static function cancellableStatuses(): array
    {
        return ['from a draft' => [false], 'from an issued revision' => [true]];
    }

    public function test_correcting_an_issued_document_supersedes_it_with_a_fresh_draft(): void
    {
        $this->authenticate();
        $created = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $documentId = $created->json('data.sales_document_id');
        $first = $created->json('data.current_version.sales_document_version_id');
        $this->postJson($this->versionEndpoint($first, 'issue'))->assertOk();

        $response = $this->postJson(self::ENDPOINT.'/'.$documentId.'/versions')->assertCreated()
            ->assertJsonPath('data.current_version.version_no', 2)
            ->assertJsonPath('data.current_version.status', 'DRAFT')
            ->assertJsonPath('data.current_version.previous_version_id', (string) $first)
            // An empty body restates the superseded content unchanged.
            ->assertJsonPath('data.current_version.total', 180000000)
            ->assertJsonPath('data.current_version.terms', 'شرایط پرداخت نقدی')
            ->assertJsonPath('data.current_version.issued_at', null)
            ->assertJsonPath('data.current_version.content_hash', null)
            ->assertJsonPath('data.current_version.customer_snapshot', null)
            ->assertJsonCount(2, 'data.versions');

        // The frozen revision keeps everything issuing stamped on it.
        $this->assertDatabaseHas('crm_sales_document_versions', ['id' => $first, 'status' => 'ISSUED']);
        $this->assertDatabaseHas('crm_sales_documents', [
            'id' => $documentId,
            'current_version_id' => $response->json('data.current_version.sales_document_version_id'),
        ]);
    }

    public function test_a_correction_may_restate_the_amount_and_clear_the_terms(): void
    {
        $this->authenticate();
        $created = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $this->postJson($this->versionEndpoint($created->json('data.current_version.sales_document_version_id'), 'issue'))->assertOk();

        $this->postJson(self::ENDPOINT.'/'.$created->json('data.sales_document_id').'/versions', [
            'total' => 200000000, 'terms' => null,
        ])->assertCreated()
            ->assertJsonPath('data.current_version.total', 200000000)
            ->assertJsonPath('data.current_version.terms', null)
            ->assertJsonPath('data.current_version.currency', 'IRR');
    }

    public function test_a_draft_is_corrected_in_place_rather_than_superseded(): void
    {
        $this->authenticate();
        $documentId = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()->json('data.sales_document_id');

        $this->postJson(self::ENDPOINT.'/'.$documentId.'/versions')->assertConflict()->assertJsonPath('error_code', 'CONFLICT');
        $this->assertDatabaseCount('crm_sales_document_versions', 1);
    }

    public function test_a_superseded_revision_no_longer_moves(): void
    {
        $this->authenticate();
        $created = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $first = $created->json('data.current_version.sales_document_version_id');
        $this->postJson($this->versionEndpoint($first, 'issue'))->assertOk();
        $this->postJson(self::ENDPOINT.'/'.$created->json('data.sales_document_id').'/versions')->assertCreated();

        $this->postJson($this->versionEndpoint($first, 'cancel'))->assertConflict()->assertJsonPath('error_code', 'CONFLICT');
        $this->assertDatabaseHas('crm_sales_document_versions', ['id' => $first, 'status' => 'ISSUED']);
    }

    public function test_an_out_of_date_document_is_neither_issued_nor_accepted(): void
    {
        $this->authenticate();
        $versionId = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()
            ->json('data.current_version.sales_document_version_id');
        DB::table('crm_sales_document_versions')->where('id', $versionId)->update(['expires_at' => '2020-01-01 00:00:00']);

        $this->postJson($this->versionEndpoint($versionId, 'issue'))->assertConflict();
        DB::table('crm_sales_document_versions')->where('id', $versionId)->update(['status' => 'ISSUED']);
        $this->postJson($this->versionEndpoint($versionId, 'accept'))->assertConflict();
        // Cancelling stays open: an expired document is exactly what an operator wants to close.
        $this->postJson($this->versionEndpoint($versionId, 'cancel'))->assertOk();
    }

    public function test_the_list_names_the_customer_and_the_revision_each_document_shows(): void
    {
        $this->authenticate();
        $created = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $this->getJson(self::ENDPOINT)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sales_document_id', $created->json('data.sales_document_id'))
            ->assertJsonPath('data.0.document_type', 'PROPOSAL')
            ->assertJsonPath('data.0.customer', ['customer_id' => '11', 'display_name' => 'پارس‌گستر آریا'])
            ->assertJsonPath('data.0.current_version.status', 'DRAFT')
            ->assertJsonPath('data.0.current_version.total', 180000000)
            ->assertJsonMissingPath('data.0.versions');
    }

    public function test_the_list_filters_by_customer_and_by_the_status_shown(): void
    {
        $this->authenticate();
        $draft = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $issued = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $this->postJson($this->versionEndpoint($issued->json('data.current_version.sales_document_version_id'), 'issue'))->assertOk();

        $this->getJson(self::ENDPOINT.'?status=ISSUED')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sales_document_id', $issued->json('data.sales_document_id'));
        $this->getJson(self::ENDPOINT.'?status=DRAFT')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sales_document_id', $draft->json('data.sales_document_id'));
        $this->getJson(self::ENDPOINT.'?customer_id=11')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::ENDPOINT.'?customer_id=12')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::ENDPOINT.'?status=CANCELLED')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_documents_of_another_tenant_are_neither_listed_nor_readable(): void
    {
        $this->authenticate();
        DB::table('crm_sales_documents')->insert([
            'id' => 77, 'hq_id' => 2, 'opportunity_id' => 9, 'customer_id' => 99, 'created_by' => 2,
            'document_no' => 'PR-OTHER-0001', 'document_type' => 'PROPOSAL',
        ]);

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::ENDPOINT.'/77')->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->getJson(self::ENDPOINT.'/404')->assertNotFound();
        $this->postJson(self::ENDPOINT.'/77/versions')->assertNotFound();
        $this->postJson($this->versionEndpoint('404', 'issue'))->assertNotFound();
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $filters, string $field): void
    {
        $this->authenticate();
        $this->getJson(self::ENDPOINT.'?'.http_build_query($filters))->assertUnprocessable()
            ->assertJsonStructure(['field_errors' => [$field]]);
    }

    public static function invalidFilters(): array
    {
        return [
            'unknown status' => [['status' => 'SENT'], 'status'],
            'zero customer' => [['customer_id' => 0], 'customer_id'],
            'non-numeric customer' => [['customer_id' => 'پارس'], 'customer_id'],
        ];
    }

    public function test_a_non_numeric_identifier_reaches_no_endpoint(): void
    {
        $this->authenticate();
        $this->getJson(self::ENDPOINT.'/abc')->assertNotFound();
        $this->postJson(self::ENDPOINT.'/abc/versions')->assertNotFound();
        $this->postJson($this->versionEndpoint('abc', 'issue'))->assertNotFound();
    }

    public function test_authentication_and_completed_password_change_are_required(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
        $this->authenticate(mustChangePassword: true);
        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');
        $this->assertDatabaseCount('crm_sales_documents', 0);
    }

    public function test_reading_needs_view_and_every_write_needs_manage(): void
    {
        $this->authenticate(permissions: ['crm.sales_document.manage']);
        $created = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $documentId = $created->json('data.sales_document_id');
        $versionId = $created->json('data.current_version.sales_document_version_id');
        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');

        $this->authenticate(permissions: ['crm.sales_document.view']);
        $this->getJson(self::ENDPOINT)->assertOk();
        $this->getJson(self::ENDPOINT.'/'.$documentId)->assertOk();
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->postJson(self::ENDPOINT.'/'.$documentId.'/versions')->assertForbidden();
        $this->postJson($this->versionEndpoint($versionId, 'issue'))->assertForbidden();
        $this->assertDatabaseCount('crm_sales_documents', 1);
    }

    #[DataProvider('deniedContexts')]
    public function test_tenant_entitlement_and_scope_checks_guard_the_screen(?string $hqId, bool $enabled, ScopeType $scope, string $error): void
    {
        $this->authenticate(hqId: $hqId, enabled: $enabled, scope: $scope);

        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('error_code', $error)->assertJsonMissingPath('data');
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden()->assertJsonPath('error_code', $error);
        $this->assertDatabaseCount('crm_sales_documents', 0);
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
            ['id' => 4, 'hq_id' => 1, 'customer_id' => 12, 'funnel_id' => 1, 'current_step_id' => 1, 'assignee_id' => 1, 'created_by' => 1, 'title' => 'فرصت سرنخ'],
            ['id' => 9, 'hq_id' => 2, 'customer_id' => 99, 'funnel_id' => 2, 'current_step_id' => 2, 'assignee_id' => 2, 'created_by' => 2, 'title' => 'فرصت سازمان دیگر'],
        ]);
    }

    private function versionEndpoint(string $versionId, string $action): string
    {
        return '/api/v1/crm/sales-document-versions/'.$versionId.'/'.$action;
    }

    private function payload(array $changes = []): array
    {
        $version = array_replace([
            'currency' => 'IRR',
            'total' => 180000000,
            'expires_at' => (new DateTimeImmutable('+7 days'))->format(DATE_ATOM),
            'terms' => 'شرایط پرداخت نقدی',
        ], $changes['version'] ?? []);
        unset($changes['version']);

        return array_replace(['opportunity_id' => 3, 'document_type' => 'PROPOSAL'], $changes, ['version' => $version]);
    }

    /** @param list<string> $permissions */
    private function authenticate(
        array $permissions = ['crm.sales_document.view', 'crm.sales_document.manage'],
        ?string $hqId = '1',
        bool $mustChangePassword = false,
        bool $enabled = true,
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'crm-sales-session', $hqId, $mustChangePassword, 'crm-sales-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('crm-sales')->andReturn($claims));
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
        $this->withToken('crm-sales');

        return $principal;
    }
}
