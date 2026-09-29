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

final class CustomerIndustriesTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_industries', 'provinces', 'cities', 'countries', 'crm_customers',
        'crm_customer_address', 'crm_customer_industry',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    public function test_the_read_returns_the_primary_industry_first(): void
    {
        $this->authenticate();
        $this->link(5, false);
        $this->link(2, true);

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data.items.0', ['industry_id' => '2', 'title' => 'Trade', 'is_primary' => true])
            ->assertJsonPath('data.items.1.industry_id', '5');
    }

    public function test_the_put_replaces_the_set_and_moves_the_primary_flag(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint(), ['items' => [$this->item(5, false), $this->item(2, true)]])->assertOk()
            ->assertJsonPath('data.items.0', ['industry_id' => '2', 'title' => 'Trade', 'is_primary' => true])
            ->assertJsonCount(2, 'data.items');

        // Industry 2 is left out, so it goes; 5 becomes the primary one.
        $this->putJson($this->endpoint(), ['items' => [$this->item(5, true)]])->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.is_primary', true);
        $this->assertDatabaseCount('crm_customer_industry', 1);
    }

    public function test_a_set_without_a_primary_industry_is_allowed_and_an_empty_one_clears_everything(): void
    {
        $this->authenticate();
        $this->link(2, true);

        $this->putJson($this->endpoint(), ['items' => [$this->item(2, false), $this->item(5, false)]])->assertOk();
        $this->assertDatabaseMissing('crm_customer_industry', ['is_primary' => true]);

        $this->putJson($this->endpoint(), ['items' => []])->assertOk()->assertJsonPath('data.items', []);
        $this->assertDatabaseCount('crm_customer_industry', 0);
        $this->putJson($this->endpoint(), [])->assertUnprocessable();
    }

    public function test_the_primary_industry_agrees_with_the_profile(): void
    {
        $this->authenticate();
        $this->putJson($this->endpoint(), ['items' => [$this->item(5, true), $this->item(2, false)]])->assertOk();

        $this->getJson('/api/v1/crm/customers/11/profile')->assertOk()->assertJsonPath('data.primary_industry.industry_id', '5');
    }

    /** @return array<string, array{0: list<array<string, mixed>>, 1: string}> */
    public static function invalidSets(): array
    {
        $item = static fn (int $id, bool $primary): array => ['industry_id' => $id, 'is_primary' => $primary];

        return [
            'two primary industries' => [[$item(5, true), $item(2, true)], 'items.1.is_primary'],
            'the same industry twice' => [[$item(5, false), $item(5, false)], 'items.1.industry_id'],
            'an inactive industry' => [[$item(6, false)], 'items.0.industry_id'],
            'an industry of another tenant' => [[$item(7, false)], 'items.0.industry_id'],
            'an unknown industry' => [[$item(99, false)], 'items.0.industry_id'],
        ];
    }

    /** @param list<array<string, mixed>> $items */
    #[DataProvider('invalidSets')]
    public function test_an_invalid_set_is_refused_and_the_stored_one_is_untouched(array $items, string $field): void
    {
        $this->authenticate();
        $this->link(2, true);
        $this->link(5, false);

        $this->putJson($this->endpoint(), ['items' => $items])->assertUnprocessable()->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_customer_industry', 2);
    }

    public function test_a_company_is_served_and_a_foreign_customer_is_missing(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint('13'), ['items' => [$this->item(2, true)]])->assertOk();
        $this->putJson($this->endpoint('21'), ['items' => [$this->item(2, true)]])->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->getJson($this->endpoint('21'))->assertNotFound();
    }

    public function test_the_read_needs_view_and_the_write_needs_edit(): void
    {
        $this->authenticate(permissions: ['customer.view']);
        $this->getJson($this->endpoint())->assertOk();
        $this->putJson($this->endpoint(), ['items' => []])->assertForbidden();

        $this->authenticate(permissions: ['customer.edit']);
        $this->getJson($this->endpoint())->assertForbidden();
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
            ['id' => 1, 'hq_code' => 'customer-industries', 'hq_title' => 'customer-industries tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer();
        $this->customer(['id' => 13, 'kind' => 'COMPANY', 'display_name' => 'Acme']);
        $this->customer(['id' => 21, 'hq_id' => 2, 'created_by' => 2]);
        foreach ([[2, 1, true, 'Trade'], [5, 1, true, 'Freight'], [6, 1, false, 'Legacy'], [7, 2, true, 'Foreign']] as [$id, $hqId, $active, $title]) {
            DB::table('crm_industries')->insert([
                'id' => $id, 'hq_id' => $hqId, 'code' => 'I'.$id, 'title' => $title, 'is_active' => $active,
                'sort_order' => $id, 'created_by' => $hqId, 'created_at' => '2026-09-28 10:00:00',
            ]);
        }
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
        return '/api/v1/crm/customers/'.$customerId.'/industries';
    }

    private function item(int $industryId, bool $primary): array
    {
        return ['industry_id' => $industryId, 'is_primary' => $primary];
    }

    private function link(int $industryId, bool $primary): void
    {
        DB::table('crm_customer_industry')->insert([
            'hq_id' => 1, 'customer_id' => 11, 'industry_id' => $industryId, 'is_primary' => $primary,
            'created_by' => 1, 'created_at' => '2026-09-28 10:00:00',
        ]);
    }


    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit'],
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-industries-session', $hqId, false, 'customer-industries-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-industries')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope(ScopeType::TENANT, null)]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-industries');

        return $principal;
    }
}
