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

final class CatalogItemTest extends TestCase
{
    private const ENDPOINT = '/api/v1/crm/catalog-items';

    private const TABLES = [
        'hq_tenants', 'users', 'crm_industries', 'crm_catalog_categories', 'crm_catalog_personas',
        'crm_catalog_sales_models', 'crm_catalog_items', 'crm_catalog_item_industry',
    ];

    public function test_an_item_is_written_with_its_industries_and_answers_the_whole_identity_card(): void
    {
        $this->authenticate();

        $response = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()
            ->assertJsonPath('data.code', 'SRV-CRM-CLOUD-01')
            ->assertJsonPath('data.kind', 'SERVICE')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.category.catalog_category_id', '1')
            ->assertJsonPath('data.category.title', 'ابری')
            ->assertJsonPath('data.buyer_persona.catalog_persona_id', '1')
            ->assertJsonPath('data.sales_model.catalog_sales_model_id', '1')
            ->assertJsonPath('data.industry_ids', ['3', '4'])
            ->assertJsonPath('data.industries.0.title', 'بانک')
            ->assertJsonPath('data.legal_notes', 'یادداشت حقوقی')
            ->assertJsonMissingPath('data.unit_id')
            ->assertJsonMissingPath('data.hq_id');

        $id = $response->json('data.catalog_item_id');
        $this->assertDatabaseHas('crm_catalog_items', ['id' => $id, 'hq_id' => 1, 'created_by' => 1, 'category_id' => 1]);
        $this->assertDatabaseHas('crm_catalog_item_industry', ['hq_id' => 1, 'catalog_item_id' => $id, 'industry_id' => 3, 'created_by' => 1]);
        $this->assertDatabaseHas('crm_catalog_item_industry', ['hq_id' => 1, 'catalog_item_id' => $id, 'industry_id' => 4, 'created_by' => 1]);
        $this->getJson(self::ENDPOINT.'/'.$id)->assertOk()->assertJsonPath('data.title', 'ابر CRM');
    }

    public function test_a_minimal_item_defaults_to_active_and_names_nothing(): void
    {
        $this->authenticate();

        $this->postJson(self::ENDPOINT, ['code' => 'MIN', 'title' => 'کالا', 'kind' => 'GOOD'])->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.industries', []);
    }

    public function test_the_code_is_unique_per_tenant(): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $this->postJson(self::ENDPOINT, $this->payload())->assertConflict()
            ->assertJsonPath('error_code', 'CONFLICT')
            ->assertJsonStructure(['field_errors' => ['code']]);
        $this->assertDatabaseCount('crm_catalog_items', 2);
    }

    #[DataProvider('rejectedItems')]
    public function test_an_item_that_cannot_stand_is_refused_without_writing_anything(array $changes, string $field): void
    {
        $this->authenticate();

        $this->postJson(self::ENDPOINT, $this->payload($changes))->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_catalog_items', 1);
        $this->assertDatabaseCount('crm_catalog_item_industry', 0);
    }

    public static function rejectedItems(): array
    {
        return [
            'retired category' => [['category_id' => 2], 'category_id'],
            'category of another tenant' => [['category_id' => 3], 'category_id'],
            'unknown category' => [['category_id' => 99], 'category_id'],
            'retired persona' => [['buyer_persona_id' => 2], 'buyer_persona_id'],
            'sales model of another tenant' => [['sales_model_id' => 2], 'sales_model_id'],
            'retired industry' => [['industry_ids' => [5]], 'industry_ids'],
            'industry of another tenant' => [['industry_ids' => [9]], 'industry_ids'],
            'industry named twice' => [['industry_ids' => [3, 3]], 'industry_ids'],
            'unknown kind' => [['kind' => 'BUNDLE'], 'kind'],
            'unknown status' => [['status' => 'DRAFT'], 'status'],
            'missing code' => [['code' => null], 'code'],
            'long code' => [['code' => str_repeat('A', 81)], 'code'],
            'missing title' => [['title' => null], 'title'],
            'long title' => [['title' => str_repeat('ا', 201)], 'title'],
        ];
    }

    public function test_the_table_answers_the_whole_filtered_set_by_code(): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $this->postJson(self::ENDPOINT, ['code' => 'A-GOOD', 'title' => 'کالای آزمایشی', 'kind' => 'GOOD', 'status' => 'INACTIVE'])->assertCreated();

        $this->getJson(self::ENDPOINT)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'A-GOOD')
            ->assertJsonPath('data.1.code', 'SRV-CRM-CLOUD-01')
            ->assertJsonPath('data.1.category.catalog_category_id', '1')
            ->assertJsonPath('data.1.industries.1.industry_id', '4')
            ->assertJsonMissingPath('data.1.description');
        $this->getJson(self::ENDPOINT.'?kind=GOOD')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::ENDPOINT.'?status=INACTIVE')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'A-GOOD');
        $this->getJson(self::ENDPOINT.'?category_id=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::ENDPOINT.'?q=cloud')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::ENDPOINT.'?q=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::ENDPOINT.'?kind=BUNDLE')->assertUnprocessable();
    }

    public function test_the_table_resolves_categories_and_industries_in_batched_queries(): void
    {
        $this->authenticate();
        foreach (['A', 'B', 'C'] as $code) {
            $this->postJson(self::ENDPOINT, $this->payload(['code' => $code]))->assertCreated();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(self::ENDPOINT)->assertOk();
        $catalogQueries = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'crm_catalog') || str_contains($query['query'], 'crm_industries'));

        // Items, categories, industry links and industries: one query each, whatever the row count.
        $this->assertLessThanOrEqual(4, count($catalogQueries));
    }

    public function test_a_patch_changes_only_what_it_names_and_an_explicit_null_clears(): void
    {
        $this->authenticate();
        $id = $this->postJson(self::ENDPOINT, $this->payload())->json('data.catalog_item_id');

        $this->patchJson(self::ENDPOINT.'/'.$id, ['title' => 'عنوان تازه', 'category_id' => null, 'description' => null])->assertOk()
            ->assertJsonPath('data.title', 'عنوان تازه')
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.category_id', null)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.delivery_terms', 'شرایط تحویل')
            ->assertJsonPath('data.industry_ids', ['3', '4']);
        $this->patchJson(self::ENDPOINT.'/'.$id, ['kind' => 'GOOD', 'status' => 'ARCHIVED'])->assertOk()
            ->assertJsonPath('data.kind', 'GOOD')->assertJsonPath('data.status', 'ARCHIVED');
    }

    public function test_a_patch_replaces_the_industries_by_their_difference(): void
    {
        $this->authenticate();
        $id = $this->postJson(self::ENDPOINT, $this->payload())->json('data.catalog_item_id');
        $keptLink = DB::table('crm_catalog_item_industry')->where(['catalog_item_id' => $id, 'industry_id' => 4])->value('id');

        $this->patchJson(self::ENDPOINT.'/'.$id, ['industry_ids' => [4]])->assertOk()->assertJsonPath('data.industry_ids', ['4']);
        $this->assertDatabaseHas('crm_catalog_item_industry', ['id' => $keptLink, 'industry_id' => 4]);
        $this->assertDatabaseMissing('crm_catalog_item_industry', ['catalog_item_id' => $id, 'industry_id' => 3]);

        $this->patchJson(self::ENDPOINT.'/'.$id, ['industry_ids' => []])->assertOk()->assertJsonPath('data.industries', []);
        $this->assertDatabaseCount('crm_catalog_item_industry', 0);
    }

    public function test_a_patch_rechecks_the_code_and_the_references_it_changes(): void
    {
        $this->authenticate();
        $id = $this->postJson(self::ENDPOINT, $this->payload())->json('data.catalog_item_id');
        $this->postJson(self::ENDPOINT, $this->payload(['code' => 'OTHER']))->assertCreated();

        $this->patchJson(self::ENDPOINT.'/'.$id, ['code' => 'OTHER'])->assertConflict()->assertJsonStructure(['field_errors' => ['code']]);
        $this->patchJson(self::ENDPOINT.'/'.$id, ['code' => 'SRV-CRM-CLOUD-01', 'title' => 'همان کد'])->assertOk();
        $this->patchJson(self::ENDPOINT.'/'.$id, ['code' => 'RENAMED'])->assertOk()->assertJsonPath('data.code', 'RENAMED');
        $this->patchJson(self::ENDPOINT.'/'.$id, ['category_id' => 2])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['category_id']]);
        $this->patchJson(self::ENDPOINT.'/'.$id, ['industry_ids' => [9]])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['industry_ids']]);
        $this->patchJson(self::ENDPOINT.'/'.$id, ['code' => null])->assertUnprocessable();
    }

    public function test_a_reference_retired_since_is_kept_by_an_unrelated_edit(): void
    {
        $this->authenticate();
        $id = $this->postJson(self::ENDPOINT, $this->payload())->json('data.catalog_item_id');
        DB::table('crm_catalog_categories')->where('id', 1)->update(['is_active' => 0]);
        DB::table('crm_industries')->where('id', 3)->update(['is_active' => 0]);

        $this->patchJson(self::ENDPOINT.'/'.$id, ['title' => 'ویرایش', 'category_id' => 1, 'industry_ids' => [3, 4]])->assertOk()
            ->assertJsonPath('data.category_id', '1');
    }

    public function test_an_empty_patch_is_refused(): void
    {
        $this->authenticate();
        $id = $this->postJson(self::ENDPOINT, $this->payload())->json('data.catalog_item_id');

        $this->patchJson(self::ENDPOINT.'/'.$id, [])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['*']]);
    }

    public function test_an_item_of_another_tenant_reads_as_missing(): void
    {
        $this->authenticate();

        $this->getJson(self::ENDPOINT.'/50')->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->getJson(self::ENDPOINT.'/999')->assertNotFound();
        $this->patchJson(self::ENDPOINT.'/50', ['title' => 'x'])->assertNotFound();
        $this->assertDatabaseHas('crm_catalog_items', ['id' => 50, 'title' => 'کالای سازمان دیگر']);
    }

    public function test_the_reference_lists_are_the_tenants_own_by_display_order(): void
    {
        $this->authenticate();

        $this->getJson('/api/v1/crm/catalog/categories')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.catalog_category_id', '2')
            ->assertJsonPath('data.0.is_active', false)
            ->assertJsonPath('data.1.sort_order', 2)
            ->assertJsonStructure(['data' => [['catalog_category_id', 'code', 'title', 'parent_id', 'is_active', 'sort_order']]]);
        $this->getJson('/api/v1/crm/catalog/categories?active=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/crm/catalog/personas')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.catalog_persona_id', '1');
        $this->getJson('/api/v1/crm/catalog/sales-models?active=1')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.catalog_sales_model_id', '1');
    }

    public function test_a_reader_may_list_but_not_write(): void
    {
        $this->authenticate(['crm.catalog.view']);

        $this->getJson(self::ENDPOINT)->assertOk();
        $this->getJson('/api/v1/crm/catalog/personas')->assertOk();
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden();
        $this->patchJson(self::ENDPOINT.'/50', ['title' => 'x'])->assertForbidden();
    }

    public function test_a_manager_without_the_view_permission_cannot_read(): void
    {
        $this->authenticate(['crm.catalog.manage']);

        $this->getJson(self::ENDPOINT)->assertForbidden();
        $this->getJson(self::ENDPOINT.'/50')->assertForbidden();
        $this->getJson('/api/v1/crm/catalog/personas')->assertForbidden();
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
    }

    public function test_a_permission_outside_tenant_scope_is_refused(): void
    {
        $this->authenticate(scope: ScopeType::NODE);

        $this->getJson(self::ENDPOINT)->assertForbidden();
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden();
    }

    public function test_a_tenant_without_the_crm_entitlement_is_refused(): void
    {
        $this->authenticate(enabled: false);

        $this->getJson(self::ENDPOINT)->assertForbidden();
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true]);
        DB::purge('sqlite');
        foreach (self::TABLES as $table) {
            (require glob(base_path('Modules/*/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CRM-CATALOG', 'hq_title' => 'Catalog tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Merchant', 'display_name' => 'Test Merchant', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Merchant', 'display_name' => 'Other Merchant', 'status' => 'ACTIVE'],
        ]);
        DB::table('crm_catalog_categories')->insert([
            ['id' => 1, 'hq_id' => 1, 'code' => 'CLOUD', 'title' => 'ابری', 'is_active' => 1, 'sort_order' => 2, 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'code' => 'OLD', 'title' => 'قدیمی', 'is_active' => 0, 'sort_order' => 1, 'created_by' => 1],
            ['id' => 3, 'hq_id' => 2, 'code' => 'FOREIGN', 'title' => 'دسته سازمان دیگر', 'is_active' => 1, 'sort_order' => 1, 'created_by' => 2],
        ]);
        DB::table('crm_catalog_personas')->insert([
            ['id' => 1, 'hq_id' => 1, 'code' => 'CTO', 'title' => 'مدیر فناوری', 'is_active' => 1, 'sort_order' => 1, 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'code' => 'OFF', 'title' => 'کنار گذاشته', 'is_active' => 0, 'sort_order' => 2, 'created_by' => 1],
        ]);
        DB::table('crm_catalog_sales_models')->insert([
            ['id' => 1, 'hq_id' => 1, 'code' => 'SUB', 'title' => 'اشتراکی', 'is_active' => 1, 'sort_order' => 1, 'created_by' => 1],
            ['id' => 2, 'hq_id' => 2, 'code' => 'SUB', 'title' => 'مدل سازمان دیگر', 'is_active' => 1, 'sort_order' => 1, 'created_by' => 2],
        ]);
        DB::table('crm_industries')->insert([
            ['id' => 3, 'hq_id' => 1, 'code' => 'BANK', 'title' => 'بانک', 'is_active' => 1, 'sort_order' => 1, 'created_by' => 1],
            ['id' => 4, 'hq_id' => 1, 'code' => 'RETAIL', 'title' => 'خرده‌فروشی', 'is_active' => 1, 'sort_order' => 2, 'created_by' => 1],
            ['id' => 5, 'hq_id' => 1, 'code' => 'RETIRED', 'title' => 'کنار گذاشته', 'is_active' => 0, 'sort_order' => 3, 'created_by' => 1],
            ['id' => 9, 'hq_id' => 2, 'code' => 'FOREIGN', 'title' => 'صنعت سازمان دیگر', 'is_active' => 1, 'sort_order' => 1, 'created_by' => 2],
        ]);
        DB::table('crm_catalog_items')->insert([
            ['id' => 50, 'hq_id' => 2, 'code' => 'SRV-CRM-CLOUD-01', 'title' => 'کالای سازمان دیگر', 'kind' => 'GOOD', 'status' => 'ACTIVE', 'created_by' => 2],
        ]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'code' => 'SRV-CRM-CLOUD-01',
            'title' => 'ابر CRM',
            'kind' => 'SERVICE',
            'status' => 'ACTIVE',
            'category_id' => 1,
            'buyer_persona_id' => 1,
            'sales_model_id' => 1,
            'description' => 'توضیحات',
            'delivery_terms' => 'شرایط تحویل',
            'lead_time' => 'دو هفته',
            'after_sales_policy' => 'پشتیبانی',
            'sla_description' => 'سطح خدمت',
            'warranty_description' => 'گارانتی',
            'legal_notes' => 'یادداشت حقوقی',
            'industry_ids' => [3, 4],
        ], $changes);
    }

    /** @param list<string> $permissions */
    private function authenticate(
        array $permissions = ['crm.catalog.view', 'crm.catalog.manage'],
        ?string $hqId = '1',
        bool $mustChangePassword = false,
        bool $enabled = true,
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'crm-catalog-session', $hqId, $mustChangePassword, 'crm-catalog-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('crm-catalog')->andReturn($claims));
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
        $this->withToken('crm-catalog');

        return $principal;
    }
}
