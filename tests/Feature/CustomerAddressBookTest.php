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
use Modules\Geography\Infrastructure\Database\Seeders\CountrySeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CustomerAddressBookTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'provinces', 'cities', 'countries', 'crm_customers',
        'crm_customer_address', 'crm_customer_departments', 'crm_positions', 'crm_relationships',
        'crm_contact_points',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    public function test_the_list_returns_the_whole_book_with_the_default_entry_first(): void
    {
        $this->authenticate();
        $this->address(['id' => 1, 'purpose' => 'BILLING', 'is_default' => false]);
        $this->address(['id' => 2, 'purpose' => 'MAIN', 'is_default' => true]);
        $this->address(['id' => 3, 'purpose' => 'WAREHOUSE', 'is_default' => false]);
        // Another customer of the same tenant keeps its own book.
        $this->customer(['id' => 12, 'display_name' => 'دیگری']);
        $this->address(['id' => 4, 'customer_id' => 12]);

        $response = $this->getJson($this->endpoint())->assertOk()
            ->assertJsonStructure(['data', 'meta', 'correlation_id']);

        self::assertSame(['2', '1', '3'], array_column($response->json('data'), 'customer_address_id'));
        self::assertSame([true, false, false], array_column($response->json('data'), 'is_default'));
    }

    public function test_one_entry_carries_every_field_of_the_form_and_the_named_places(): void
    {
        $this->authenticate();
        $this->address([
            'id' => 1, 'purpose' => 'MAIN', 'province_id' => 1, 'city_id' => 1, 'postal_code' => '1234567890',
            'address_text' => 'تهران، نشانی نمایشی', 'plaque' => '0۱۲ب', 'unit' => '04',
            'latitude' => '35.7000000', 'longitude' => '51.4000000',
        ]);

        $this->getJson($this->endpoint('11', '1'))->assertOk()
            ->assertJsonPath('data.customer_address_id', '1')
            ->assertJsonPath('data.country_code', 'IR')
            ->assertJsonPath('data.country', ['country_id' => $this->countryId('IR'), 'name' => 'ایران'])
            ->assertJsonPath('data.purpose', 'MAIN')
            ->assertJsonPath('data.province_id', '1')
            ->assertJsonPath('data.city_id', '1')
            ->assertJsonPath('data.province', ['province_id' => '1', 'name' => 'تهران'])
            ->assertJsonPath('data.city', ['city_id' => '1', 'name' => 'تهران'])
            ->assertJsonPath('data.foreign_region', null)
            ->assertJsonPath('data.foreign_city', null)
            ->assertJsonPath('data.address_text', 'تهران، نشانی نمایشی')
            ->assertJsonPath('data.postal_code', '1234567890')
            // Text columns, so a leading zero and a Persian letter both survive.
            ->assertJsonPath('data.plaque', '0۱۲ب')
            ->assertJsonPath('data.unit', '04')
            ->assertJsonPath('data.latitude', '35.7000000')
            ->assertJsonPath('data.longitude', '51.4000000')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.hq_id');
    }

    public function test_the_first_entry_of_a_customer_becomes_the_default_one_whatever_the_form_said(): void
    {
        $this->authenticate();

        $response = $this->postJson($this->endpoint(), $this->payload(['is_default' => false]))
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.purpose', 'نشانی اصلی')
            ->assertJsonPath('data.country_code', 'IR')
            ->assertJsonPath('data.address_text', 'تهران، نشانی نمایشی');

        $this->assertDatabaseHas('crm_customer_address', [
            'id' => $response->json('data.customer_address_id'), 'hq_id' => 1, 'customer_id' => 11,
            'created_by' => 1, 'country_code' => 'IR', 'province_id' => 1, 'city_id' => 1,
            'purpose' => 'نشانی اصلی', 'is_default' => true, 'plaque' => '12', 'unit' => '3',
        ]);
    }

    public function test_a_later_entry_stays_beside_the_default_one_unless_it_claims_the_flag(): void
    {
        $this->authenticate();
        $this->address(['id' => 1, 'is_default' => true]);

        $this->postJson($this->endpoint(), $this->payload())->assertCreated()->assertJsonPath('data.is_default', false);
        $this->assertDatabaseHas('crm_customer_address', ['id' => 1, 'is_default' => true]);

        $claimed = $this->postJson($this->endpoint(), $this->payload(['is_default' => true]))
            ->assertCreated()->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('crm_customer_address', ['id' => 1, 'is_default' => false]);
        $this->assertDatabaseHas('crm_customer_address', ['id' => $claimed->json('data.customer_address_id'), 'is_default' => true]);
        $this->assertDatabaseCount('crm_customer_address', 3);
    }

    public function test_a_foreign_entry_keeps_its_typed_region_and_city_instead_of_reference_geography(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), [
            'country_code' => 'ae', 'purpose' => 'MAIN', 'address_text' => 'دبی، نشانی نمایشی',
            'foreign_region' => 'امارت دبی', 'foreign_city' => 'دبی',
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'AE')
            ->assertJsonPath('data.country', ['country_id' => $this->countryId('AE'), 'name' => 'امارات متحدهٔ عربی'])
            ->assertJsonPath('data.province_id', null)
            ->assertJsonPath('data.city_id', null)
            ->assertJsonPath('data.foreign_region', 'امارت دبی')
            ->assertJsonPath('data.foreign_city', 'دبی');
    }

    public function test_an_iranian_entry_may_name_no_province_and_no_city_at_all(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), ['country_code' => 'IR', 'purpose' => 'MAIN', 'address_text' => 'نشانی بدون شهر'])
            ->assertCreated()
            ->assertJsonPath('data.province_id', null)
            ->assertJsonPath('data.city_id', null)
            ->assertJsonPath('data.postal_code', null);
    }

    public function test_a_change_is_judged_as_the_whole_address_it_leaves_behind(): void
    {
        $this->authenticate();
        $this->address(['id' => 1, 'province_id' => 1, 'city_id' => 1]);

        // The country alone moves abroad, so the reference city it leaves behind is refused.
        $this->patchJson($this->endpoint('11', '1'), ['country_code' => 'AE'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('field_errors.city_id', ['Select a reference city only for Iran.']);

        $this->patchJson($this->endpoint('11', '1'), [
            'country_code' => 'AE', 'province_id' => null, 'city_id' => null, 'foreign_city' => 'دبی',
        ])->assertOk()
            ->assertJsonPath('data.country_code', 'AE')
            ->assertJsonPath('data.province_id', null)
            ->assertJsonPath('data.city_id', null)
            ->assertJsonPath('data.foreign_city', 'دبی');
    }

    public function test_untouched_fields_survive_a_change_and_an_explicit_null_clears_one(): void
    {
        $this->authenticate();
        $this->address(['id' => 1, 'postal_code' => '1234567890', 'plaque' => '12', 'unit' => '3', 'address_text' => 'نشانی اول']);

        $this->patchJson($this->endpoint('11', '1'), ['unit' => null, 'purpose' => 'BILLING'])->assertOk()
            ->assertJsonPath('data.purpose', 'BILLING')
            ->assertJsonPath('data.unit', null)
            ->assertJsonPath('data.plaque', '12')
            ->assertJsonPath('data.postal_code', '1234567890')
            ->assertJsonPath('data.address_text', 'نشانی اول');
    }

    public function test_the_default_flag_moves_to_another_entry_and_is_never_simply_dropped(): void
    {
        $this->authenticate();
        $this->address(['id' => 1, 'is_default' => true]);
        $this->address(['id' => 2, 'is_default' => false, 'purpose' => 'BILLING']);

        $this->patchJson($this->endpoint('11', '1'), ['is_default' => false])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.is_default', ['Make another address the default one instead of clearing the flag on this one.']);

        $this->patchJson($this->endpoint('11', '2'), ['is_default' => true])->assertOk()
            ->assertJsonPath('data.is_default', true);
        $this->assertDatabaseHas('crm_customer_address', ['id' => 1, 'is_default' => false]);
        $this->assertDatabaseHas('crm_customer_address', ['id' => 2, 'is_default' => true]);
    }

    public function test_a_change_that_carries_no_field_is_refused(): void
    {
        $this->authenticate();
        $this->address(['id' => 1]);

        $response = $this->patchJson($this->endpoint('11', '1'), [])->assertStatus(422);

        // assertJsonPath reads '*' as a wildcard, so the catch-all key is taken off the body itself.
        self::assertSame(['Send at least one field to change on the address.'], $response->json('field_errors')['*']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_an_incoherent_address_is_refused(array $overrides, string $field): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), $this->payload($overrides))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_customer_address', 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            'no country' => [['country_code' => null], 'country_code'],
            'unknown country' => [['country_code' => 'ZZ', 'province_id' => null, 'city_id' => null], 'country_code'],
            'no purpose' => [['purpose' => null], 'purpose'],
            'no written address' => [['address_text' => null], 'address_text'],
            'foreign region inside Iran' => [['foreign_region' => 'امارت دبی'], 'foreign_region'],
            'foreign city inside Iran' => [['foreign_city' => 'دبی'], 'foreign_city'],
            'postal code of nine digits' => [['postal_code' => '123456789'], 'postal_code'],
            'city without its province' => [['province_id' => null], 'province_id'],
            'city of another province' => [['province_id' => 2], 'province_id'],
            'latitude without longitude' => [['longitude' => null], 'longitude'],
            'longitude without latitude' => [['latitude' => null], 'latitude'],
            'latitude out of range' => [['latitude' => 91], 'latitude'],
            'longitude out of range' => [['longitude' => 181], 'longitude'],
        ];
    }

    public function test_a_customer_or_an_address_outside_the_reach_of_the_actor_is_reported_as_missing(): void
    {
        $this->authenticate();
        $this->customer(['id' => 13, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'مشتری سازمان دیگر']);
        $this->address(['id' => 1, 'customer_id' => 13, 'hq_id' => 2, 'created_by' => 2]);
        $this->address(['id' => 2, 'customer_id' => 11]);

        foreach ([$this->endpoint('13'), $this->endpoint('99')] as $endpoint) {
            $this->getJson($endpoint)->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        }
        // The address exists, but under another tenant's customer.
        $this->getJson($this->endpoint('11', '1'))->assertNotFound();
        $this->patchJson($this->endpoint('11', '1'), ['purpose' => 'BILLING'])->assertNotFound();
        $this->postJson($this->endpoint('13'), $this->payload())->assertNotFound();
        $this->assertDatabaseHas('crm_customer_address', ['id' => 1, 'purpose' => 'MAIN']);
    }

    #[DataProvider('deniedContexts')]
    public function test_access_is_denied_without_the_tenant_module_permission_and_scope(
        ?string $hqId, bool $enabled, array $permissions, ScopeType $scope, string $errorCode): void
    {
        $this->authenticate($hqId, $enabled, $permissions, $scope);
        $this->address(['id' => 1]);

        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', $errorCode);
        $this->postJson($this->endpoint(), $this->payload())->assertForbidden()->assertJsonPath('error_code', $errorCode);
    }

    public static function deniedContexts(): array
    {
        return [
            'no tenant' => [null, true, ['customer.view', 'customer.edit'], ScopeType::TENANT, 'TENANT_ACCESS_DENIED'],
            'disabled module' => ['1', false, ['customer.view', 'customer.edit'], ScopeType::TENANT, 'ENTITLEMENT_DISABLED'],
            'no permission' => ['1', true, ['customer.create'], ScopeType::TENANT, 'PERMISSION_DENIED'],
            'node scope' => ['1', true, ['customer.view', 'customer.edit'], ScopeType::NODE, 'SCOPE_ACCESS_DENIED'],
            'self scope' => ['1', true, ['customer.view', 'customer.edit'], ScopeType::SelfScope, 'SCOPE_ACCESS_DENIED'],
        ];
    }

    public function test_reading_needs_the_view_permission_and_writing_needs_the_edit_one(): void
    {
        $this->authenticate(permissions: ['customer.view']);
        $this->address(['id' => 1]);

        $this->getJson($this->endpoint())->assertOk();
        $this->postJson($this->endpoint(), $this->payload())->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->patchJson($this->endpoint('11', '1'), ['purpose' => 'BILLING'])->assertForbidden();
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
        $this->seed(CountrySeeder::class);
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CUSTOMER-ADDRESS', 'hq_title' => 'Customer address tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        DB::table('provinces')->insert([
            ['id' => 1, 'legacy_province_code' => '01', 'name_fa' => 'تهران', 'normalized_name' => 'تهران', 'latitude' => 35.7, 'longitude' => 51.4],
            ['id' => 2, 'legacy_province_code' => '02', 'name_fa' => 'فارس', 'normalized_name' => 'فارس', 'latitude' => 29.6, 'longitude' => 52.5],
        ]);
        DB::table('cities')->insert(['id' => 1, 'province_id' => 1, 'legacy_city_code' => '001', 'name_fa' => 'تهران', 'normalized_name' => 'تهران']);
        $this->customer();
        // English field errors, so the assertions below read as lang/en/api.php writes them.
        $this->withHeader('Accept-Language', 'en');
    }

    private function endpoint(string $customerId = '11', ?string $addressId = null): string
    {
        return '/api/v1/crm/customers/'.$customerId.'/addresses'.($addressId === null ? '' : '/'.$addressId);
    }

    /** Read rather than hard-coded: the seeder numbers the 249 reference countries in file order. */
    private function countryId(string $code): string
    {
        return (string) DB::table('countries')->where('country_code', $code)->value('id');
    }

    private function payload(array $overrides = []): array
    {
        return array_filter(array_replace([
            'country_code' => 'IR',
            'purpose' => 'نشانی اصلی',
            'province_id' => 1,
            'city_id' => 1,
            'postal_code' => '1234567890',
            'address_text' => 'تهران، نشانی نمایشی',
            'plaque' => '12',
            'unit' => '3',
            'latitude' => 35.7,
            'longitude' => 51.4,
        ], $overrides), static fn (mixed $value): bool => $value !== null);
    }

    private function customer(array $attributes = []): void
    {
        DB::table('crm_customers')->insert(array_replace([
            'id' => 11, 'hq_id' => 1, 'created_by' => 1, 'assignee_id' => null, 'kind' => 'COMPANY',
            'phase' => 'CUSTOMER', 'lifecycle' => 'ACTIVE', 'display_name' => 'پارس‌گستر آریا',
            'created_at' => '2026-09-28 10:00:00', 'updated_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function address(array $attributes = []): void
    {
        DB::table('crm_customer_address')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'country_code' => 'IR', 'province_id' => 1,
            'city_id' => 1, 'address_text' => 'تهران، نشانی نمایشی', 'purpose' => 'MAIN',
            'is_default' => true, 'created_by' => 1,
        ], $attributes));
    }

    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit'],
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-address-session', $hqId, false, 'customer-address-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-address')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-address');

        return $principal;
    }
}
