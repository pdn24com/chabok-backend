<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Modules\Customer\Application\UseCases\GetCustomerDetail\GetCustomerDetailCommand;
use Modules\Customer\Application\UseCases\GetCustomerDetail\GetCustomerDetailHandler;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AccessTokenClaims;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CustomerDetailTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'provinces', 'cities', 'crm_industries', 'crm_catalog_categories',
        'crm_catalog_personas', 'crm_catalog_sales_models', 'crm_catalog_items', 'crm_customers',
        'crm_customer_address', 'crm_customer_departments', 'crm_positions', 'crm_relationships',
        'crm_contact_points', 'crm_customer_industry', 'crm_sales_funnel', 'crm_sales_funnel_steps',
        'crm_opportunities', 'crm_tasks',
    ];

    public function test_detail_returns_the_record_its_address_industry_and_open_work(): void
    {
        $this->authenticate();
        $this->customer();
        $this->address();
        $this->mobile();
        $this->industryLink();
        $this->tasks();
        $this->opportunities();

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.customer', [
                'customer_id' => '11',
                'kind' => 'COMPANY',
                'phase' => 'CUSTOMER',
                'lifecycle' => 'ACTIVE',
                'display_name' => 'پارس‌گستر آریا',
                'customer_code' => 'C-1001',
                'assignee' => ['user_id' => '12', 'display_name' => 'سارا احمدی'],
                'converted_at' => null,
                'open_opportunities_counts' => 1,
                'open_tasks_counts' => 3,
            ])
            ->assertJsonPath('data.default_address', ['city' => 'تهران'])
            ->assertJsonPath('data.primary_industry', ['industry_id' => '2', 'title' => 'بازرگانی'])
            ->assertJsonPath('data.opportunities', [[
                'opportunity_id' => '3',
                'title' => 'قرارداد سالانه',
                'step' => ['title' => 'مذاکره', 'outcome_type' => 'OPEN'],
                'amount' => 180000000,
            ]])
            ->assertJsonPath('data.missing', [])
            ->assertJsonStructure(['data', 'meta', 'correlation_id'])
            ->assertJsonMissingPath('data.customer.hq_id')
            ->assertJsonMissingPath('data.customer.created_by');
    }

    public function test_open_tasks_are_listed_by_deadline_with_undated_work_last(): void
    {
        $this->authenticate();
        $this->customer();
        $this->tasks();

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.customer.open_tasks_counts', 3)
            ->assertJsonPath('data.open_tasks', [
                ['task_id' => '3', 'title' => 'پیگیری پیش‌فاکتور', 'status' => 'WAITING_CUSTOMER', 'due_at' => '2026-09-30T09:00:00.000000Z'],
                ['task_id' => '1', 'title' => 'تماس اول', 'status' => 'OPEN', 'due_at' => '2026-10-01T09:00:00.000000Z'],
                ['task_id' => '2', 'title' => 'آماده‌سازی پیشنهاد', 'status' => 'IN_PROGRESS', 'due_at' => null],
            ]);
    }

    public function test_closed_work_is_left_out_of_both_the_lists_and_the_counts(): void
    {
        $this->authenticate();
        $this->customer();
        $this->tasks();
        $this->opportunities();

        $response = $this->getJson($this->endpoint())->assertOk();
        self::assertSame(['3', '1', '2'], array_column($response->json('data.open_tasks'), 'task_id'));
        self::assertSame(['3'], array_column($response->json('data.opportunities'), 'opportunity_id'));
        self::assertSame(3, $response->json('data.customer.open_tasks_counts'));
        self::assertSame(1, $response->json('data.customer.open_opportunities_counts'));
        $this->assertDatabaseCount('crm_tasks', 5);
        $this->assertDatabaseCount('crm_opportunities', 3);
    }

    public function test_a_record_without_owner_address_industry_or_work_returns_empty_sections(): void
    {
        $this->authenticate();
        $this->customer([
            'assignee_id' => null, 'display_name' => null, 'customer_code' => null,
            'first_name' => null, 'family_name' => null, 'converted_at' => null,
        ]);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.customer.assignee', null)
            ->assertJsonPath('data.customer.display_name', null)
            ->assertJsonPath('data.customer.customer_code', null)
            ->assertJsonPath('data.customer.open_tasks_counts', 0)
            ->assertJsonPath('data.customer.open_opportunities_counts', 0)
            ->assertJsonPath('data.default_address', null)
            ->assertJsonPath('data.primary_industry', null)
            ->assertJsonPath('data.open_tasks', [])
            ->assertJsonPath('data.opportunities', [])
            ->assertJsonPath('data.missing', [
                'customer_code', 'display_name', 'first_name', 'family_name',
                'mobile', 'assignee', 'primary_industry', 'default_address',
            ]);
    }

    public function test_an_address_without_its_own_details_reports_only_those_two_gaps(): void
    {
        $this->authenticate();
        $this->customer();
        $this->address(['postal_code' => null, 'address_text' => null]);
        $this->mobile();
        $this->industryLink();

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.default_address', ['city' => 'تهران'])
            ->assertJsonPath('data.missing', ['postal_code', 'address_text']);
    }

    public function test_a_blank_mobile_or_industry_is_reported_as_missing(): void
    {
        $this->authenticate();
        $this->customer();
        $this->address();
        $this->mobile(['value' => '   ']);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.missing', ['mobile', 'primary_industry']);
    }

    public function test_a_secondary_industry_alone_still_counts_as_a_gap(): void
    {
        $this->authenticate();
        $this->customer();
        $this->address();
        $this->mobile();
        $this->industryLink(['is_primary' => false]);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.primary_industry', null)
            ->assertJsonPath('data.missing', ['primary_industry']);
    }

    public function test_conversion_time_is_returned_when_the_record_became_a_customer(): void
    {
        $this->authenticate();
        $this->customer(['converted_at' => '2026-09-20 08:30:00']);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.customer.converted_at', '2026-09-20T08:30:00.000000Z');
    }

    public function test_a_foreign_address_falls_back_to_the_typed_city_name(): void
    {
        $this->authenticate();
        $this->customer();
        $this->address(['country_code' => 'AE', 'province_id' => null, 'city_id' => null, 'foreign_city' => 'دبی']);

        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.default_address', ['city' => 'دبی']);
    }

    public function test_only_the_default_address_and_the_primary_industry_are_returned(): void
    {
        $this->authenticate();
        $this->customer();
        $this->address();
        $this->address(['id' => 2, 'purpose' => 'BILLING', 'is_default' => false, 'city_id' => 2]);
        $this->industryLink();
        $this->industryLink(['id' => 2, 'industry_id' => 4, 'is_primary' => false]);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.default_address', ['city' => 'تهران'])
            ->assertJsonPath('data.primary_industry', ['industry_id' => '2', 'title' => 'بازرگانی']);
    }

    #[DataProvider('unreachableCustomers')]
    public function test_a_missing_or_foreign_customer_is_not_found(string $customerId): void
    {
        $actor = $this->authenticate();
        $this->customer();
        $this->customer(['id' => 99, 'hq_id' => 2, 'created_by' => 2, 'assignee_id' => 2]);

        $this->getJson($this->endpoint($customerId))->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');

        try {
            $this->app->make(GetCustomerDetailHandler::class)->handle(new GetCustomerDetailCommand($actor, $customerId));
            self::fail('A customer outside the actor tenant must not be readable through the use case either.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
            self::assertSame(404, $exception->httpStatus);
        }
    }

    public static function unreachableCustomers(): array
    {
        return ['unknown record' => ['404'], 'record of another tenant' => ['99']];
    }

    public function test_a_non_numeric_identifier_does_not_reach_the_endpoint(): void
    {
        $this->authenticate();
        $this->customer();

        $this->getJson('/api/v1/crm/customers/abc/detail')->assertNotFound();
    }

    public function test_authentication_and_completed_password_change_are_required(): void
    {
        $this->customer();
        $this->getJson($this->endpoint())->assertUnauthorized();
        $this->authenticate(mustChangePassword: true);
        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');
    }

    #[DataProvider('deniedContexts')]
    public function test_reading_one_customer_requires_view_access_to_the_tenant(?string $hqId, bool $enabled, bool $permission, ScopeType $scope, string $error): void
    {
        $actor = $this->authenticate(hqId: $hqId, enabled: $enabled, permission: $permission, scope: $scope);
        $this->customer();

        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', $error)->assertJsonMissingPath('data');

        try {
            $this->app->make(GetCustomerDetailHandler::class)->handle(new GetCustomerDetailCommand($actor, '11'));
            self::fail('Direct use-case calls must enforce tenant, entitlement, and permission checks.');
        } catch (ApiException $exception) {
            self::assertSame($error, $exception->errorCode->value);
            self::assertSame(403, $exception->httpStatus);
        }
    }

    public static function deniedContexts(): array
    {
        return [
            'no tenant' => [null, true, true, ScopeType::TENANT, 'TENANT_ACCESS_DENIED'],
            'disabled module' => ['1', false, true, ScopeType::TENANT, 'ENTITLEMENT_DISABLED'],
            'create without view permission' => ['1', true, false, ScopeType::TENANT, 'PERMISSION_DENIED'],
            'node scope' => ['1', true, true, ScopeType::NODE, 'SCOPE_ACCESS_DENIED'],
            'self scope' => ['1', true, true, ScopeType::SelfScope, 'SCOPE_ACCESS_DENIED'],
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
        (require base_path('Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php'))->up();
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CUSTOMER-DETAIL', 'hq_title' => 'Customer detail tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Reader', 'display_name' => 'Test Reader', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Reader', 'display_name' => 'Other Reader', 'status' => 'ACTIVE'],
            ['id' => 12, 'hq_id' => 1, 'first_name' => 'سارا', 'last_name' => 'احمدی', 'display_name' => 'سارا احمدی', 'status' => 'ACTIVE'],
        ]);
        DB::table('provinces')->insert(['id' => 1, 'legacy_province_code' => '01', 'name_fa' => 'تهران', 'normalized_name' => 'تهران', 'latitude' => 35.7, 'longitude' => 51.4]);
        DB::table('cities')->insert([
            ['id' => 1, 'province_id' => 1, 'legacy_city_code' => '001', 'name_fa' => 'تهران', 'normalized_name' => 'تهران'],
            ['id' => 2, 'province_id' => 1, 'legacy_city_code' => '002', 'name_fa' => 'شهریار', 'normalized_name' => 'شهریار'],
        ]);
        DB::table('crm_industries')->insert([
            ['id' => 2, 'hq_id' => 1, 'code' => 'TRADE', 'title' => 'بازرگانی', 'is_active' => true, 'created_by' => 1],
            ['id' => 4, 'hq_id' => 1, 'code' => 'RETAIL', 'title' => 'خرده‌فروشی', 'is_active' => true, 'created_by' => 1],
        ]);
        DB::table('crm_sales_funnel')->insert(['id' => 1, 'hq_id' => 1, 'code' => 'DEFAULT', 'title' => 'قیف فروش', 'created_by' => 1]);
        DB::table('crm_sales_funnel_steps')->insert([
            ['id' => 1, 'hq_id' => 1, 'funnel_id' => 1, 'code' => 'NEGOTIATION', 'title' => 'مذاکره', 'sort_order' => 1, 'outcome_type' => 'OPEN', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'funnel_id' => 1, 'code' => 'WON', 'title' => 'برنده', 'sort_order' => 2, 'outcome_type' => 'WON', 'created_by' => 1],
            ['id' => 3, 'hq_id' => 1, 'funnel_id' => 1, 'code' => 'LOST', 'title' => 'بازنده', 'sort_order' => 3, 'outcome_type' => 'LOST', 'created_by' => 1],
        ]);
    }

    private function endpoint(string $customerId = '11'): string
    {
        return '/api/v1/crm/customers/'.$customerId.'/detail';
    }

    private function customer(array $attributes = []): void
    {
        DB::table('crm_customers')->insert(array_replace([
            'id' => 11, 'hq_id' => 1, 'created_by' => 1, 'assignee_id' => 12, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER',
            'lifecycle' => 'ACTIVE', 'display_name' => 'پارس‌گستر آریا', 'customer_code' => 'C-1001',
            'first_name' => 'علی', 'family_name' => 'احمدی',
            'created_at' => '2026-09-28 10:00:00', 'updated_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function address(array $attributes = []): void
    {
        DB::table('crm_customer_address')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'country_code' => 'IR', 'province_id' => 1, 'city_id' => 1,
            'postal_code' => '1234567890', 'address_text' => 'تهران، خیابان ولیعصر، پلاک ۱۰',
            'purpose' => 'MAIN', 'is_default' => true, 'created_by' => 1,
        ], $attributes));
    }

    private function mobile(array $attributes = []): void
    {
        DB::table('crm_contact_points')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'type' => 'MOBILE', 'value' => '09121234567',
            'normalized_value' => '+989121234567', 'scope' => 'WORK', 'is_default' => true,
            'status' => 'ACTIVE', 'identifier_kind' => 'PHONE', 'created_by' => 1,
        ], $attributes));
    }

    private function industryLink(array $attributes = []): void
    {
        DB::table('crm_customer_industry')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'industry_id' => 2, 'is_primary' => true, 'created_by' => 1,
        ], $attributes));
    }

    private function tasks(): void
    {
        DB::table('crm_tasks')->insert([
            ['id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1, 'title' => 'تماس اول', 'status' => 'OPEN', 'due_at' => '2026-10-01 09:00:00'],
            ['id' => 2, 'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1, 'title' => 'آماده‌سازی پیشنهاد', 'status' => 'IN_PROGRESS', 'due_at' => null],
            ['id' => 3, 'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1, 'title' => 'پیگیری پیش‌فاکتور', 'status' => 'WAITING_CUSTOMER', 'due_at' => '2026-09-30 09:00:00'],
            ['id' => 4, 'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1, 'title' => 'جلسهٔ انجام‌شده', 'status' => 'COMPLETED', 'due_at' => '2026-09-01 09:00:00'],
            ['id' => 5, 'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1, 'title' => 'کار لغوشده', 'status' => 'CANCELLED', 'due_at' => '2026-09-02 09:00:00'],
        ]);
    }

    private function opportunities(): void
    {
        DB::table('crm_opportunities')->insert([
            ['id' => 3, 'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 1, 'assignee_id' => 12, 'created_by' => 1, 'title' => 'قرارداد سالانه', 'amount' => 180000000],
            ['id' => 4, 'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 2, 'assignee_id' => 12, 'created_by' => 1, 'title' => 'فرصت برنده', 'amount' => 90000000],
            ['id' => 5, 'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 3, 'assignee_id' => 12, 'created_by' => 1, 'title' => 'فرصت بازنده', 'amount' => null],
        ]);
    }

    private function authenticate(?string $hqId = '1', bool $mustChangePassword = false, bool $enabled = true, bool $permission = true, ScopeType $scope = ScopeType::TENANT): AuthenticatedPrincipal
    {
        $claims = new AccessTokenClaims('1', 'customer-detail-session', $hqId, $mustChangePassword, 'customer-detail-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-detail')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permission ? ['customer.view'] : ['customer.create'],
            permissionScopes: ['customer.view' => [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]],
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-detail');

        return $principal;
    }
}
