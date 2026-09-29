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
 * The document archive and the documents tab of a record, which is the same list narrowed to one
 * resource: the category tree, registering a document with what it is attached to, changing it,
 * retiring it and attaching it somewhere else.
 */
final class DocumentArchiveTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_sales_funnel', 'crm_sales_funnel_steps',
        'crm_opportunities', 'document_categories', 'documents', 'document_links',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    // 2027-03-20 at midnight UTC, the spelling every date in these payloads travels in.
    private const EXPIRES_ON = 1805587200;

    public function test_the_category_tree_is_served_flat_with_parents_before_their_children(): void
    {
        $this->authenticate();
        $this->category(['id' => 1, 'title' => 'اسناد فنی']);
        $this->category(['id' => 2, 'title' => 'اسناد حقوقی و قرارداد']);
        $this->category(['id' => 3, 'title' => 'پیوست قرارداد', 'parent_id' => 2]);
        $this->category(['id' => 4, 'title' => 'دستهٔ بازنشسته', 'active' => false]);

        $all = $this->getJson('/api/v1/crm/document-categories')->assertOk();
        // A client builds whatever nesting it draws, so every node names its parent and roots come first.
        self::assertSame(['1', '2', '4', '3'], array_column($all->json('data'), 'document_category_id'));
        self::assertSame([null, null, null, '2'], array_column($all->json('data'), 'parent_id'));

        $active = $this->getJson('/api/v1/crm/document-categories?active=1')->assertOk();
        self::assertSame(['1', '2', '3'], array_column($active->json('data'), 'document_category_id'));
    }

    public function test_a_document_is_registered_together_with_what_it_belongs_to(): void
    {
        $this->authenticate();
        $this->category(['id' => 2, 'title' => 'اسناد حقوقی و قرارداد']);

        $response = $this->postJson('/api/v1/crm/documents', [
            'title' => 'پیش‌نویس قرارداد همکاری',
            'classification' => 'محرمانه',
            'category_id' => 2,
            'reference_no' => 'CN-1405-01',
            'expires_on' => self::EXPIRES_ON,
            'links' => [
                ['resource_type' => 'CUSTOMER', 'resource_id' => 11, 'purpose' => 'متن قرارداد'],
                ['resource_type' => 'OPPORTUNITY', 'resource_id' => 3],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'پیش‌نویس قرارداد همکاری')
            ->assertJsonPath('data.classification', 'محرمانه')
            ->assertJsonPath('data.reference_no', 'CN-1405-01')
            ->assertJsonPath('data.expires_on', self::EXPIRES_ON)
            // A document is registered active; retiring it is its own action.
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.category', ['document_category_id' => '2', 'parent_id' => null, 'title' => 'اسناد حقوقی و قرارداد', 'active' => true]);

        $links = $response->json('data.links');
        self::assertSame(['CUSTOMER', 'OPPORTUNITY'], array_column($links, 'resource_type'));
        self::assertSame(['11', '3'], array_column($links, 'resource_id'));
        self::assertSame(['متن قرارداد', null], array_column($links, 'purpose'));
        $this->assertDatabaseHas('documents', [
            'id' => $response->json('data.document_id'), 'hq_id' => 1, 'created_by' => 1, 'expires_on' => '2027-03-20',
        ]);
        $this->assertDatabaseCount('document_links', 2);
    }

    public function test_a_document_may_be_registered_attached_to_nothing_and_linked_later(): void
    {
        $this->authenticate();

        $document = $this->postJson('/api/v1/crm/documents', ['title' => 'معرفی شرکت', 'classification' => 'عادی'])
            ->assertCreated()
            ->assertJsonPath('data.links', [])
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.expires_on', null);

        $this->postJson($this->linkEndpoint($document->json('data.document_id')), [
            'resource_type' => 'CUSTOMER', 'resource_id' => 11, 'purpose' => 'معرفی',
        ])->assertCreated()
            ->assertJsonPath('data.resource_type', 'CUSTOMER')
            ->assertJsonPath('data.resource_id', '11')
            ->assertJsonPath('data.purpose', 'معرفی');

        // The database keeps one link per pair, so the same attachment a second time is a conflict.
        $this->postJson($this->linkEndpoint($document->json('data.document_id')), ['resource_type' => 'CUSTOMER', 'resource_id' => 11])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICT');
        $this->assertDatabaseCount('document_links', 1);
    }

    #[DataProvider('unusableDrafts')]
    public function test_a_document_attached_to_something_unusable_is_refused(array $changes, string $field): void
    {
        $this->authenticate();
        $this->category(['id' => 4, 'title' => 'دستهٔ بازنشسته', 'active' => false]);
        $this->customer(['id' => 13, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'مشتری سازمان دیگر']);

        $this->postJson('/api/v1/crm/documents', array_replace([
            'title' => 'سند نمونه', 'classification' => 'عادی',
        ], $changes))->assertStatus(422)->assertJsonStructure(['field_errors' => [$field]]);

        // Nothing is written at all, so a document is never filed half attached.
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_links', 0);
    }

    public static function unusableDrafts(): array
    {
        return [
            'retired category' => [['category_id' => 4], 'category_id'],
            'category of another tenant' => [['category_id' => 99], 'category_id'],
            'customer of another tenant' => [['links' => [['resource_type' => 'CUSTOMER', 'resource_id' => 13]]], 'links.0.resource_id'],
            'record that does not exist' => [['links' => [['resource_type' => 'OPPORTUNITY', 'resource_id' => 99]]], 'links.0.resource_id'],
            'a kind outside the registry' => [['links' => [['resource_type' => 'CONTRACT', 'resource_id' => 1]]], 'links.0.resource_type'],
            'the same record twice' => [['links' => [
                ['resource_type' => 'CUSTOMER', 'resource_id' => 11],
                ['resource_type' => 'CUSTOMER', 'resource_id' => 11],
            ]], 'links.1.resource_id'],
        ];
    }

    public function test_the_archive_is_filtered_by_status_by_category_and_by_one_search_term(): void
    {
        $this->authenticate();
        $this->category(['id' => 1, 'title' => 'اسناد فنی']);
        $this->document(['id' => 1, 'title' => 'معرفی شرکت', 'category_id' => 1, 'reference_no' => 'DOC-1']);
        $this->document(['id' => 2, 'title' => 'پیش‌نویس قرارداد', 'reference_no' => 'CN-1405-01']);
        $this->document(['id' => 3, 'title' => 'سند بایگانی', 'status' => 'ARCHIVED']);

        $all = $this->getJson('/api/v1/crm/documents')->assertOk()
            ->assertJsonPath('meta.pagination.total', 3);
        // Newest first, which for a registry that never renumbers is the order they were filed in reversed.
        self::assertSame(['3', '2', '1'], array_column($all->json('data'), 'document_id'));

        $active = $this->getJson('/api/v1/crm/documents?status=ACTIVE')->assertOk();
        self::assertSame(['2', '1'], array_column($active->json('data'), 'document_id'));

        $byCategory = $this->getJson('/api/v1/crm/documents?category_id=1')->assertOk();
        self::assertSame(['1'], array_column($byCategory->json('data'), 'document_id'));

        // One term against the title and the reference number alike, which is how an operator searches.
        $byTitle = $this->getJson('/api/v1/crm/documents?q=قرارداد')->assertOk();
        self::assertSame(['2'], array_column($byTitle->json('data'), 'document_id'));
        $byReference = $this->getJson('/api/v1/crm/documents?q=CN-1405')->assertOk();
        self::assertSame(['2'], array_column($byReference->json('data'), 'document_id'));
    }

    public function test_narrowing_the_archive_to_one_record_is_the_documents_tab_of_that_record(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'display_name' => 'دیگری']);
        $this->document(['id' => 1, 'title' => 'معرفی شرکت']);
        $this->document(['id' => 2, 'title' => 'سند مشتری دیگر']);
        $this->document(['id' => 3, 'title' => 'سند فرصت']);
        $this->link(['id' => 1, 'document_id' => 1, 'resource_type' => 'CUSTOMER', 'resource_id' => 11, 'purpose' => 'معرفی']);
        $this->link(['id' => 2, 'document_id' => 2, 'resource_type' => 'CUSTOMER', 'resource_id' => 12]);
        $this->link(['id' => 3, 'document_id' => 3, 'resource_type' => 'OPPORTUNITY', 'resource_id' => 3]);
        // The same document reaching two records is the point of a shared archive.
        $this->link(['id' => 4, 'document_id' => 1, 'resource_type' => 'OPPORTUNITY', 'resource_id' => 3]);

        $ofCustomer = $this->getJson('/api/v1/crm/documents?resource_type=CUSTOMER&resource_id=11')->assertOk();
        self::assertSame(['1'], array_column($ofCustomer->json('data'), 'document_id'));
        // Every link of the document is listed, including the ones to records other than the one asked for.
        self::assertSame(['CUSTOMER', 'OPPORTUNITY'], array_column($ofCustomer->json('data.0.links'), 'resource_type'));

        $ofOpportunity = $this->getJson('/api/v1/crm/documents?resource_type=OPPORTUNITY&resource_id=3')->assertOk();
        self::assertSame(['3', '1'], array_column($ofOpportunity->json('data'), 'document_id'));
    }

    public function test_half_a_resource_pair_narrows_to_nothing_usable_and_is_refused(): void
    {
        $this->authenticate();

        $this->getJson('/api/v1/crm/documents?resource_type=CUSTOMER')->assertStatus(422)
            ->assertJsonStructure(['field_errors' => ['resource_id']]);
        $this->getJson('/api/v1/crm/documents?resource_id=11')->assertStatus(422)
            ->assertJsonStructure(['field_errors' => ['resource_type']]);
    }

    public function test_untouched_fields_survive_a_change_and_an_explicit_null_clears_one(): void
    {
        $this->authenticate();
        $this->category(['id' => 1, 'title' => 'اسناد فنی']);
        $this->document(['id' => 1, 'title' => 'معرفی شرکت', 'category_id' => 1, 'reference_no' => 'DOC-1', 'expires_on' => '2027-03-20']);

        $this->patchJson($this->endpoint('1'), ['title' => 'معرفی تازه', 'expires_on' => null])->assertOk()
            ->assertJsonPath('data.title', 'معرفی تازه')
            ->assertJsonPath('data.expires_on', null)
            ->assertJsonPath('data.reference_no', 'DOC-1')
            ->assertJsonPath('data.category_id', '1')
            ->assertJsonPath('data.classification', 'عادی');

        $response = $this->patchJson($this->endpoint('1'), [])->assertStatus(422);
        // assertJsonPath reads '*' as a wildcard, so the catch-all key is taken off the body itself.
        self::assertSame(['Send at least one field to change on the document.'], $response->json('field_errors')['*']);
    }

    public function test_retiring_a_document_is_its_own_action_and_happens_once(): void
    {
        $this->authenticate();
        $this->document(['id' => 1, 'title' => 'معرفی شرکت']);

        // A routine edit cannot retire a document by accident.
        $this->patchJson($this->endpoint('1'), ['status' => 'ARCHIVED'])->assertStatus(422)
            ->assertJsonStructure(['field_errors' => ['status']]);

        $this->postJson($this->endpoint('1').'/archive')->assertOk()->assertJsonPath('data.status', 'ARCHIVED');
        $this->assertDatabaseHas('documents', ['id' => 1, 'status' => 'ARCHIVED']);

        $repeated = $this->postJson($this->endpoint('1').'/archive')->assertStatus(422);
        self::assertSame(['This document is already archived.'], $repeated->json('field_errors')['*']);
    }

    public function test_an_archived_document_is_a_closed_record(): void
    {
        $this->authenticate();
        $this->document(['id' => 1, 'title' => 'معرفی شرکت', 'status' => 'ARCHIVED']);

        $changed = $this->patchJson($this->endpoint('1'), ['title' => 'نام تازه'])->assertStatus(422);
        self::assertSame(['An archived document can no longer be changed.'], $changed->json('field_errors')['*']);
        $attached = $this->postJson($this->linkEndpoint('1'), ['resource_type' => 'CUSTOMER', 'resource_id' => 11])->assertStatus(422);
        self::assertSame(['An archived document is not attached to anything new.'], $attached->json('field_errors')['*']);

        // The links it already carries are untouched: a record that once held it still says so.
        $this->assertDatabaseCount('document_links', 0);
        $this->assertDatabaseHas('documents', ['id' => 1, 'title' => 'معرفی شرکت']);
    }

    public function test_a_document_is_never_attached_to_a_record_nobody_can_look_up(): void
    {
        $this->authenticate();
        $this->document(['id' => 1, 'title' => 'معرفی شرکت']);
        $this->customer(['id' => 13, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'مشتری سازمان دیگر']);

        $this->postJson($this->linkEndpoint('1'), ['resource_type' => 'CUSTOMER', 'resource_id' => 13])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.resource_id', ['Select an existing record of this tenant to attach the document to.']);
        $this->postJson($this->linkEndpoint('1'), ['resource_type' => 'OPPORTUNITY', 'resource_id' => 99])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.resource_id', ['Select an existing record of this tenant to attach the document to.']);
        $this->assertDatabaseCount('document_links', 0);
    }

    public function test_reading_needs_the_view_permission_and_filing_needs_the_manage_one(): void
    {
        $this->authenticate(permissions: ['crm.document.view']);
        $this->document(['id' => 1, 'title' => 'معرفی شرکت']);

        $this->getJson('/api/v1/crm/documents')->assertOk();
        $this->getJson('/api/v1/crm/document-categories')->assertOk();
        $this->postJson('/api/v1/crm/documents', ['title' => 'سند', 'classification' => 'عادی'])
            ->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->patchJson($this->endpoint('1'), ['title' => 'نام تازه'])->assertForbidden();
        $this->postJson($this->endpoint('1').'/archive')->assertForbidden();
        $this->postJson($this->linkEndpoint('1'), ['resource_type' => 'CUSTOMER', 'resource_id' => 11])->assertForbidden();

        // The customer permissions say nothing about the archive.
        $this->authenticate(permissions: ['customer.view', 'customer.edit']);
        $this->getJson('/api/v1/crm/documents')->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
    }

    #[DataProvider('deniedContexts')]
    public function test_access_is_denied_without_the_tenant_module_and_scope(
        ?string $hqId, bool $enabled, ScopeType $scope, string $errorCode): void
    {
        $this->authenticate($hqId, $enabled, scope: $scope);

        $this->getJson('/api/v1/crm/documents')->assertForbidden()->assertJsonPath('error_code', $errorCode);
        $this->postJson('/api/v1/crm/documents', ['title' => 'سند', 'classification' => 'عادی'])
            ->assertForbidden()->assertJsonPath('error_code', $errorCode);
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

    public function test_a_document_or_a_record_outside_the_reach_of_the_actor_is_reported_as_missing(): void
    {
        $this->authenticate();
        $this->customer(['id' => 13, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'مشتری سازمان دیگر']);
        $this->document(['id' => 1, 'hq_id' => 2, 'created_by' => 2, 'title' => 'سند سازمان دیگر']);

        $this->patchJson($this->endpoint('1'), ['title' => 'نام تازه'])->assertNotFound()
            ->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->postJson($this->endpoint('99').'/archive')->assertNotFound();
        $this->postJson($this->linkEndpoint('1'), ['resource_type' => 'CUSTOMER', 'resource_id' => 11])->assertNotFound();
        // Narrowing to a record of another tenant answers as if the record did not exist, so the archive
        // cannot be used to discover what a neighbouring tenant owns.
        $this->getJson('/api/v1/crm/documents?resource_type=CUSTOMER&resource_id=13')->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => 1, 'title' => 'سند سازمان دیگر']);
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
            ['id' => 1, 'hq_code' => 'DOC-ARCHIVE', 'hq_title' => 'Document archive tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer();
        DB::table('crm_sales_funnel')->insert(['id' => 1, 'hq_id' => 1, 'code' => 'GENERAL', 'title' => 'قیف فروش عمومی', 'is_active' => true, 'created_by' => 1]);
        DB::table('crm_sales_funnel_steps')->insert(['id' => 11, 'hq_id' => 1, 'funnel_id' => 1, 'code' => 'DISCOVERY', 'title' => 'کشف نیاز', 'sort_order' => 1, 'outcome_type' => 'OPEN', 'is_active' => true, 'created_by' => 1]);
        DB::table('crm_opportunities')->insert(['id' => 3, 'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 11, 'assignee_id' => 1, 'title' => 'قرارداد ارسال دوره‌ای', 'created_by' => 1]);
        // English field errors, so the assertions above read as lang/en/api.php writes them.
        $this->withHeader('Accept-Language', 'en');
    }

    private function endpoint(string $documentId): string
    {
        return '/api/v1/crm/documents/'.$documentId;
    }

    private function linkEndpoint(string $documentId): string
    {
        return $this->endpoint($documentId).'/links';
    }

    private function customer(array $attributes = []): void
    {
        DB::table('crm_customers')->insert(array_replace([
            'id' => 11, 'hq_id' => 1, 'created_by' => 1, 'assignee_id' => null, 'kind' => 'COMPANY',
            'phase' => 'CUSTOMER', 'lifecycle' => 'ACTIVE', 'display_name' => 'پارس‌گستر آریا',
            'created_at' => '2026-09-28 10:00:00', 'updated_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function category(array $attributes = []): void
    {
        DB::table('document_categories')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'parent_id' => null, 'title' => 'اسناد فنی', 'active' => true, 'created_by' => 1,
        ], $attributes));
    }

    private function document(array $attributes = []): void
    {
        DB::table('documents')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'title' => 'معرفی شرکت', 'category_id' => null, 'classification' => 'عادی',
            'reference_no' => null, 'expires_on' => null, 'status' => 'ACTIVE', 'created_by' => 1,
        ], $attributes));
    }

    private function link(array $attributes = []): void
    {
        DB::table('document_links')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'document_id' => 1, 'resource_type' => 'CUSTOMER',
            'resource_id' => 11, 'purpose' => null, 'created_by' => 1,
        ], $attributes));
    }

    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['crm.document.view', 'crm.document.manage'],
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'doc-archive-session', $hqId, false, 'doc-archive-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('doc-archive')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('doc-archive');

        return $principal;
    }
}
