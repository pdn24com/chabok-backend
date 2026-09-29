<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Modules\Customer\Application\UseCases\ListCustomers\ListCustomersCommand;
use Modules\Customer\Application\UseCases\ListCustomers\ListCustomersHandler;
use Modules\Foundation\Application\Contracts\AccessTokenServiceInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AccessTokenClaims;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ListCustomersTest extends TestCase
{
    private const ENDPOINT = '/api/v1/customers';

    public function test_customer_list_is_paginated_ordered_and_isolated_by_tenant(): void
    {
        $this->authenticate();
        $first = $this->customer(['display_name' => 'مشتری اول', 'updated_at' => '2026-09-28 12:00:00']);
        $second = $this->customer([
            'display_name' => 'شرکت نمونه', 'phase' => 'CUSTOMER', 'kind' => 'COMPANY', 'assignee_id' => 12,
            'customer_code' => '000123', 'lifecycle' => 'INACTIVE', 'updated_at' => '2026-09-28 12:00:00',
        ]);
        $older = $this->customer(['display_name' => 'مشتری قدیمی', 'lifecycle' => 'ARCHIVED', 'updated_at' => '2026-09-28 11:00:00']);
        $this->customer(['hq_id' => 2, 'created_by' => 2, 'display_name' => 'سازمان دیگر', 'updated_at' => '2026-09-28 13:00:00']);

        $pageOne = $this->getJson(self::ENDPOINT.'?per_page=2&hq_id=2')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.customer_id', $second)
            ->assertJsonPath('data.0.display_name', 'شرکت نمونه')
            ->assertJsonPath('data.0.phase', 'CUSTOMER')
            ->assertJsonPath('data.0.kind', 'COMPANY')
            ->assertJsonPath('data.0.customer_code', '000123')
            ->assertJsonPath('data.0.lifecycle', 'INACTIVE')
            ->assertJsonPath('data.0.assignee', ['user_id' => '12', 'display_name' => 'سارا احمدی'])
            ->assertJsonPath('data.0.updated_at', '2026-09-28T12:00:00.000000Z')
            ->assertJsonPath('data.1.customer_id', $first)
            ->assertJsonPath('data.1.assignee', null)
            ->assertJsonPath('meta.pagination', ['page' => 1, 'page_size' => 2, 'total' => 3, 'total_pages' => 2])
            ->assertJsonMissingPath('data.0.hq_id')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.assignee_id')
            ->assertJsonMissingPath('data.0.address')
            ->assertJsonMissingPath('data.0.id');
        $pageTwo = $this->getJson(self::ENDPOINT.'?page=2&per_page=2')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_id', $older)
            ->assertJsonPath('data.0.lifecycle', 'ARCHIVED')
            ->assertJsonPath('meta.pagination', ['page' => 2, 'page_size' => 2, 'total' => 3, 'total_pages' => 2]);
        self::assertSame([], array_intersect(array_column($pageOne->json('data'), 'customer_id'), array_column($pageTwo->json('data'), 'customer_id')));
        $this->getJson(self::ENDPOINT.'?page=3&per_page=2')->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.pagination.total', 3);
    }

    public function test_empty_list_has_pagination_metadata(): void
    {
        $this->authenticate();
        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.pagination', ['page' => 1, 'page_size' => 25, 'total' => 0, 'total_pages' => 1]);
    }

    public function test_default_and_maximum_page_sizes(): void
    {
        $this->authenticate();
        for ($index = 0; $index < 26; $index++) {
            $this->customer();
        }
        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.pagination.total', 26)->assertJsonPath('meta.pagination.total_pages', 2);
        $this->getJson(self::ENDPOINT.'?per_page=100')->assertOk()->assertJsonCount(26, 'data')
            ->assertJsonPath('meta.pagination.page_size', 100);
    }

    public function test_nullable_legacy_fields_are_returned_as_null(): void
    {
        $this->authenticate();
        $this->customer(['display_name' => null, 'customer_code' => null, 'updated_at' => null]);
        $this->getJson(self::ENDPOINT)->assertOk()
            ->assertJsonPath('data.0.display_name', null)
            ->assertJsonPath('data.0.customer_code', null)
            ->assertJsonPath('data.0.assignee', null)
            ->assertJsonPath('data.0.updated_at', null);
    }

    #[DataProvider('fieldFilters')]
    public function test_each_customer_field_can_be_filtered(array $filters): void
    {
        $this->authenticate();
        $match = $this->customer([
            'display_name' => 'شرکت چابک', 'customer_code' => '000123', 'assignee_id' => 12,
            'phase' => 'CUSTOMER', 'kind' => 'COMPANY', 'lifecycle' => 'INACTIVE',
        ]);
        $this->customer(['display_name' => 'مشتری دیگر', 'customer_code' => '999999', 'assignee_id' => 1]);

        $this->getJson(self::ENDPOINT.'?'.http_build_query($filters))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer_id', $match)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public static function fieldFilters(): array
    {
        return [
            'display name substring' => [['display_name' => 'چابک']],
            'customer code substring' => [['customer_code' => '0012']],
            'zero is a filter' => [['customer_code' => '0']],
            'phase' => [['phase' => 'CUSTOMER']],
            'kind' => [['kind' => 'COMPANY']],
            'lifecycle' => [['lifecycle' => 'INACTIVE']],
            'assignee' => [['assignee_id' => 12]],
        ];
    }

    public function test_filters_are_combined_before_pagination_and_keep_tenant_isolation(): void
    {
        $this->authenticate();
        $attributes = [
            'display_name' => 'شرکت چابک', 'customer_code' => 'CRM-001', 'assignee_id' => 12,
            'phase' => 'CUSTOMER', 'kind' => 'COMPANY', 'lifecycle' => 'ACTIVE',
        ];
        $first = $this->customer($attributes);
        $second = $this->customer(array_replace($attributes, ['customer_code' => 'CRM-002']));
        $this->customer(array_replace($attributes, ['customer_code' => 'CRM-003', 'lifecycle' => 'ARCHIVED']));
        $this->customer(array_replace($attributes, ['customer_code' => 'CRM-004', 'phase' => 'LEAD']));
        $this->customer(array_replace($attributes, ['customer_code' => 'CRM-005', 'kind' => 'PERSON']));
        $this->customer(array_replace($attributes, ['customer_code' => 'CRM-006', 'display_name' => 'سایر']));
        $this->customer(array_replace($attributes, ['customer_code' => 'CRM-007', 'assignee_id' => 1]));
        $this->customer(array_replace($attributes, ['customer_code' => 'OTHER']));
        $this->customer(array_replace($attributes, ['hq_id' => 2, 'created_by' => 2, 'assignee_id' => 2]));
        $query = http_build_query([
            'display_name' => 'چابک', 'customer_code' => 'CRM-', 'phase' => 'CUSTOMER', 'assignee_id' => 12,
            'kind' => 'COMPANY', 'lifecycle' => 'ACTIVE', 'updated_at' => '2026-09-28', 'per_page' => 1,
        ]);

        $this->getJson(self::ENDPOINT.'?'.$query)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_id', $second)
            ->assertJsonPath('meta.pagination', ['page' => 1, 'page_size' => 1, 'total' => 2, 'total_pages' => 2]);
        $this->getJson(self::ENDPOINT.'?'.$query.'&page=2')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.customer_id', $first)
            ->assertJsonPath('meta.pagination.total', 2);
    }

    #[DataProvider('dateFilters')]
    public function test_updated_at_filters_include_complete_utc_days(array $filters, array $expected): void
    {
        $this->authenticate();
        foreach ([
            'before' => '2026-09-27 23:59:59',
            'start' => '2026-09-28 00:00:00',
            'end' => '2026-09-28 23:59:59.999999',
            'after' => '2026-09-29 00:00:00',
            'unknown' => null,
        ] as $code => $updatedAt) {
            $this->customer(['customer_code' => $code, 'updated_at' => $updatedAt]);
        }

        $response = $this->getJson(self::ENDPOINT.'?'.http_build_query($filters))->assertOk()
            ->assertJsonPath('meta.pagination.total', count($expected));
        self::assertSame($expected, array_column($response->json('data'), 'customer_code'));
    }

    public static function dateFilters(): array
    {
        return [
            'single date' => [['updated_at' => '2026-09-28'], ['end', 'start']],
            'same-day range' => [['updated_at_from' => '2026-09-28', 'updated_at_to' => '2026-09-28'], ['end', 'start']],
            'lower bound only' => [['updated_at_from' => '2026-09-28'], ['after', 'end', 'start']],
            'upper bound only' => [['updated_at_to' => '2026-09-28'], ['end', 'start', 'before']],
            'range' => [['updated_at_from' => '2026-09-27', 'updated_at_to' => '2026-09-28'], ['end', 'start', 'before']],
        ];
    }

    public function test_empty_filters_are_ignored_and_no_matches_return_empty_pagination(): void
    {
        $this->authenticate();
        $this->customer();
        $this->getJson(self::ENDPOINT.'?'.http_build_query([
            'display_name' => '', 'customer_code' => '', 'phase' => '', 'assignee_id' => '',
            'kind' => '', 'lifecycle' => '', 'updated_at' => '', 'updated_at_from' => '', 'updated_at_to' => '',
        ]))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.pagination.total', 1);
        $this->getJson(self::ENDPOINT.'?customer_code=NO-MATCH')->assertOk()->assertJsonPath('data', [])
            ->assertJsonPath('meta.pagination', ['page' => 1, 'page_size' => 25, 'total' => 0, 'total_pages' => 1]);
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(array $filters, string $field): void
    {
        $this->authenticate();
        $this->getJson(self::ENDPOINT.'?'.http_build_query($filters))->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')->assertJsonStructure(['field_errors' => [$field]]);
    }

    public static function invalidFilters(): array
    {
        return [
            'long display name' => [['display_name' => str_repeat('ا', 201)], 'display_name'],
            'array display name' => [['display_name' => ['چابک']], 'display_name'],
            'long customer code' => [['customer_code' => str_repeat('C', 81)], 'customer_code'],
            'array customer code' => [['customer_code' => ['C-001']], 'customer_code'],
            'unknown phase' => [['phase' => 'OTHER'], 'phase'],
            'unknown kind' => [['kind' => 'OTHER'], 'kind'],
            'unknown lifecycle' => [['lifecycle' => 'OTHER'], 'lifecycle'],
            'zero assignee' => [['assignee_id' => 0], 'assignee_id'],
            'non-numeric assignee' => [['assignee_id' => 'سارا'], 'assignee_id'],
            'array assignee' => [['assignee_id' => [12]], 'assignee_id'],
            'array phase' => [['phase' => ['LEAD']], 'phase'],
            'malformed date' => [['updated_at' => 'invalid'], 'updated_at'],
            'nonexistent date' => [['updated_at' => '2026-02-30'], 'updated_at'],
            'datetime instead of date' => [['updated_at' => '2026-09-28T12:00:00Z'], 'updated_at'],
            'invalid start date' => [['updated_at_from' => 'invalid'], 'updated_at_from'],
            'invalid end date' => [['updated_at_to' => 'invalid'], 'updated_at_to'],
            'reversed range' => [['updated_at_from' => '2026-09-29', 'updated_at_to' => '2026-09-28'], 'updated_at_to'],
        ];
    }

    #[DataProvider('invalidPagination')]
    public function test_invalid_pagination_is_rejected(array $query, string $field): void
    {
        $this->authenticate();
        $this->getJson(self::ENDPOINT.'?'.http_build_query($query))->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => [$field]]);
    }

    public static function invalidPagination(): array
    {
        return [
            'zero page' => [['page' => 0], 'page'],
            'negative page' => [['page' => -1], 'page'],
            'non-integer page' => [['page' => 'invalid'], 'page'],
            'fractional page' => [['page' => 1.5], 'page'],
            'array page' => [['page' => [1]], 'page'],
            'zero page size' => [['per_page' => 0], 'per_page'],
            'oversized page' => [['per_page' => 101], 'per_page'],
            'non-integer page size' => [['per_page' => 'invalid'], 'per_page'],
        ];
    }

    public function test_authentication_and_completed_password_change_are_required(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        $this->authenticate(mustChangePassword: true);
        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');
    }

    #[DataProvider('deniedContexts')]
    public function test_customer_list_requires_view_access_to_the_tenant(?string $hqId, bool $enabled, bool $permission, ScopeType $scope, string $error): void
    {
        $actor = $this->authenticate(hqId: $hqId, enabled: $enabled, permission: $permission, scope: $scope);
        $this->customer();
        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('error_code', $error)->assertJsonMissingPath('data');

        try {
            $this->app->make(ListCustomersHandler::class)->handle(new ListCustomersCommand($actor));
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
        foreach (['hq_tenants', 'users', 'provinces', 'cities', 'crm_customers', 'crm_customer_address'] as $table) {
            (require glob(base_path('Modules/*/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require base_path('Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php'))->up();
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CUSTOMER-LIST', 'hq_title' => 'Customer list tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Reader', 'display_name' => 'Test Reader', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Reader', 'display_name' => 'Other Reader', 'status' => 'ACTIVE'],
            ['id' => 12, 'hq_id' => 1, 'first_name' => 'سارا', 'last_name' => 'احمدی', 'display_name' => 'سارا احمدی', 'status' => 'ACTIVE'],
        ]);
    }

    private function customer(array $attributes = []): string
    {
        return (string) DB::table('crm_customers')->insertGetId(array_replace([
            'hq_id' => 1, 'created_by' => 1, 'display_name' => 'مشتری نمونه', 'kind' => 'PERSON', 'phase' => 'LEAD',
            'customer_code' => null, 'created_at' => '2026-09-28 10:00:00', 'updated_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function authenticate(?string $hqId = '1', bool $mustChangePassword = false, bool $enabled = true, bool $permission = true, ScopeType $scope = ScopeType::TENANT): AuthenticatedPrincipal
    {
        $claims = new AccessTokenClaims('1', 'customer-list-session', $hqId, $mustChangePassword, 'customer-list-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-reader')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permission ? ['customer.view'] : ['customer.create'],
            permissionScopes: ['customer.view' => [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]],
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-reader');

        return $principal;
    }
}
