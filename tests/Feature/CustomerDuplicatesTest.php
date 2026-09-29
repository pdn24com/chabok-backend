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

final class CustomerDuplicatesTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'provinces', 'cities', 'countries', 'crm_customers', 'crm_customer_address',
        'crm_customer_departments', 'crm_positions', 'crm_relationships', 'crm_contact_points',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    public function test_a_mobile_finds_the_customers_holding_it_in_any_spelling(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1, 'customer_id' => 12]);

        foreach (['09120000123', '+989120000123', '0912 000 0123', '۰۹۱۲۰۰۰۰۱۲۳'] as $mobile) {
            $this->getJson($this->endpoint(['mobile' => $mobile]))->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.items.0', ['customer_id' => '12', 'display_name' => 'Holder', 'phase' => 'CUSTOMER', 'matched_on' => 'MOBILE']);
        }
    }

    public function test_an_email_is_compared_lower_cased(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1, 'customer_id' => 12, 'type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => 'Shared@X.com', 'normalized_value' => 'shared@x.com']);

        $this->getJson($this->endpoint(['email' => 'SHARED@x.com']))->assertOk()->assertJsonPath('data.items.0.matched_on', 'EMAIL');
    }

    public function test_a_customer_found_by_both_is_reported_once_as_a_mobile_match(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1, 'customer_id' => 12]);
        $this->contactPoint(['id' => 2, 'customer_id' => 12, 'type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => 'a@x.com', 'normalized_value' => 'a@x.com']);

        $this->getJson($this->endpoint(['mobile' => '09120000123', 'email' => 'a@x.com']))->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.matched_on', 'MOBILE');
    }

    public function test_an_empty_email_counts_as_absent_and_inactive_or_foreign_holders_are_not_reported(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1, 'customer_id' => 12, 'status' => 'INACTIVE']);
        $this->customer(['id' => 21, 'hq_id' => 2, 'created_by' => 2]);
        $this->contactPoint(['id' => 2, 'hq_id' => 2, 'customer_id' => 21, 'created_by' => 2]);

        $this->getJson($this->endpoint(['mobile' => '09120000123', 'email' => '']))->assertOk()->assertJsonPath('data.items', []);
    }

    public function test_at_most_twenty_customers_are_returned_newest_first(): void
    {
        $this->authenticate();
        for ($id = 100; $id < 125; $id++) {
            $this->customer(['id' => $id, 'display_name' => 'P'.$id]);
            $this->contactPoint(['customer_id' => $id, 'type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => 'm@m.com', 'normalized_value' => 'm@m.com', 'is_default' => false]);
        }

        $response = $this->getJson($this->endpoint(['email' => 'm@m.com']))->assertOk()->assertJsonCount(20, 'data.items');

        self::assertSame('124', $response->json('data.items.0.customer_id'));
    }

    public function test_the_lookup_needs_a_usable_mobile_or_email(): void
    {
        $this->authenticate();

        $this->getJson($this->endpoint())->assertUnprocessable();
        $this->getJson($this->endpoint(['mobile' => 'abc']))->assertUnprocessable()->assertJsonStructure(['field_errors' => ['mobile']]);
        $this->getJson($this->endpoint(['email' => 'nope']))->assertUnprocessable();
    }

    public function test_the_lookup_needs_view_and_is_not_swallowed_by_the_customer_id_routes(): void
    {
        $this->authenticate(permissions: ['customer.edit']);
        $this->getJson($this->endpoint(['mobile' => '09120000123']))->assertForbidden();

        $this->authenticate();
        $this->getJson('/api/v1/crm/customers/duplicates/detail')->assertNotFound();
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
            ['id' => 1, 'hq_code' => 'customer-duplicates', 'hq_title' => 'customer-duplicates tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer();
        $this->customer(['id' => 12, 'phase' => 'CUSTOMER', 'display_name' => 'Holder']);
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

    /** @param array<string, string> $query */
    private function endpoint(array $query = []): string
    {
        return '/api/v1/crm/customers/duplicates'.($query === [] ? '' : '?'.http_build_query($query));
    }

    private function contactPoint(array $attributes = []): void
    {
        DB::table('crm_contact_points')->insert(array_replace([
            'hq_id' => 1, 'customer_id' => 12, 'type' => 'MOBILE', 'identifier_kind' => 'PHONE',
            'value' => '09120000123', 'normalized_value' => '+989120000123', 'scope' => 'PERSONAL',
            'is_default' => true, 'status' => 'ACTIVE', 'created_by' => 1, 'created_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }


    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit'],
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-duplicates-session', $hqId, false, 'customer-duplicates-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-duplicates')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope(ScopeType::TENANT, null)]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-duplicates');

        return $principal;
    }
}
