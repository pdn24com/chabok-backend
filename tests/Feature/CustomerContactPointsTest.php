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

final class CustomerContactPointsTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'provinces', 'cities', 'countries', 'crm_customers', 'crm_customer_address',
        'crm_customer_departments', 'crm_positions', 'crm_relationships', 'crm_contact_points',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    public function test_the_list_returns_the_set_with_the_default_entry_first(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1, 'type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => 'a@x.com', 'normalized_value' => 'a@x.com', 'is_default' => false, 'priority' => 1]);
        $this->contactPoint(['id' => 2, 'is_default' => true, 'priority' => 5]);
        $this->contactPoint(['id' => 3, 'customer_id' => 12, 'value' => '09120000001', 'normalized_value' => '+989120000001']);

        $response = $this->getJson($this->endpoint())->assertOk();

        self::assertSame(['2', '1'], array_column($response->json('data.items'), 'contact_point_id'));
    }

    public function test_the_put_creates_updates_and_deletes_and_normalises_on_the_server(): void
    {
        $this->authenticate();
        $created = $this->putJson($this->endpoint(), ['items' => [
            $this->item(),
            $this->item(['type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => ' Nima@Example.COM ', 'priority' => 2, 'verified_manually' => true]),
            $this->item(['type' => 'INSTAGRAM', 'identifier_kind' => 'USERNAME', 'value' => '@Nima_X', 'is_default' => false]),
        ]])->assertOk();

        $items = collect($created->json('data.items'))->keyBy('type');
        self::assertSame('+989120000123', $items['MOBILE']['normalized_value']);
        self::assertSame('nima@example.com', $items['EMAIL']['normalized_value']);
        self::assertSame('nima_x', $items['INSTAGRAM']['normalized_value']);
        self::assertNotNull($items['EMAIL']['verified_manually_at']);

        // The second call keeps the mobile (edited), drops the other two and adds a landline.
        $this->putJson($this->endpoint(), ['items' => [
            $this->item(['id' => $items['MOBILE']['contact_point_id'], 'priority' => 9]),
            $this->item(['type' => 'PHONE', 'identifier_kind' => 'PHONE', 'value' => '09121111111', 'is_default' => false]),
        ]])->assertOk()->assertJsonCount(2, 'data.items');

        $this->assertDatabaseCount('crm_contact_points', 2);
        $this->assertDatabaseMissing('crm_contact_points', ['type' => 'EMAIL']);
        $this->assertDatabaseHas('crm_contact_points', ['id' => $items['MOBILE']['contact_point_id'], 'priority' => 9]);
    }

    public function test_the_default_flag_can_move_between_two_channels_in_one_write(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1, 'is_default' => true]);

        $response = $this->putJson($this->endpoint(), ['items' => [
            $this->item(['id' => 1, 'is_default' => false]),
            $this->item(['value' => '09123334444', 'is_default' => true]),
        ]])->assertOk();

        $defaults = array_values(array_filter($response->json('data.items'), static fn (array $item): bool => $item['is_default']));
        self::assertCount(1, $defaults);
        self::assertSame('+989123334444', $defaults[0]['normalized_value']);
    }

    public function test_an_empty_list_removes_every_channel(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1]);

        $this->putJson($this->endpoint(), ['items' => []])->assertOk()->assertJsonPath('data.items', []);
        $this->assertDatabaseCount('crm_contact_points', 0);
        $this->putJson($this->endpoint(), [])->assertUnprocessable();
    }

    public function test_the_first_manual_review_time_is_kept_and_cleared_with_the_flag(): void
    {
        $this->authenticate();
        $first = $this->putJson($this->endpoint(), ['items' => [$this->item(['verified_manually' => true])]])->assertOk();
        $stamp = $first->json('data.items.0.verified_manually_at');
        $id = $first->json('data.items.0.contact_point_id');
        self::assertNotNull($stamp);

        $this->putJson($this->endpoint(), ['items' => [$this->item(['id' => $id, 'verified_manually' => true])]])
            ->assertOk()->assertJsonPath('data.items.0.verified_manually_at', $stamp);
        $this->putJson($this->endpoint(), ['items' => [$this->item(['id' => $id, 'verified_manually' => false])]])
            ->assertOk()->assertJsonPath('data.items.0.verified_manually_at', null);
    }

    /** @return array<string, array{0: list<array<string, mixed>>, 1: string}> */
    public static function invalidSets(): array
    {
        $mobile = ['type' => 'MOBILE', 'identifier_kind' => 'PHONE', 'value' => '09120000123', 'scope' => 'PERSONAL', 'is_default' => true];

        return [
            'identifier kind does not match the type' => [[array_replace($mobile, ['identifier_kind' => 'EMAIL'])], 'items.0.identifier_kind'],
            'unusable mobile number' => [[array_replace($mobile, ['value' => '123'])], 'items.0.value'],
            'invalid email' => [[array_replace($mobile, ['type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => 'nope'])], 'items.0.value'],
            'address reference without an address' => [[array_replace($mobile, ['type' => 'ADDRESS_REFERENCE', 'identifier_kind' => 'ADDRESS', 'value' => 'home'])], 'items.0.address_id'],
            'address of another person' => [[array_replace($mobile, ['type' => 'ADDRESS_REFERENCE', 'identifier_kind' => 'ADDRESS', 'value' => 'home', 'address_id' => 2, 'is_default' => false])], 'items.0.address_id'],
            'relationship of another person' => [[array_replace($mobile, ['relationship_id' => 2])], 'items.0.relationship_id'],
            'two defaults for one channel and scope' => [[$mobile, array_replace($mobile, ['value' => '09125555555'])], 'items.1.is_default'],
            'the same number twice across scopes' => [[array_replace($mobile, ['is_default' => false]), array_replace($mobile, ['value' => '+98 912 000 0123', 'scope' => 'WORK', 'is_default' => false])], 'items.1.value'],
            'an ID that is not the person\'s own' => [[array_replace($mobile, ['id' => 9999])], 'items.0.id'],
            'an inactive channel as default' => [[array_replace($mobile, ['status' => 'INACTIVE'])], 'items.0.is_default'],
        ];
    }

    /** @param list<array<string, mixed>> $items */
    #[DataProvider('invalidSets')]
    public function test_an_invalid_set_is_refused_and_nothing_changes(array $items, string $field): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1]);
        $this->contactPoint(['id' => 2, 'customer_id' => 12, 'value' => '09129990000', 'normalized_value' => '+989129990000']);
        DB::table('crm_customer_address')->insert([
            ['id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'country_code' => 'IR', 'address_text' => 'a', 'purpose' => 'MAIN', 'is_default' => true, 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'customer_id' => 12, 'country_code' => 'IR', 'address_text' => 'a', 'purpose' => 'MAIN', 'is_default' => true, 'created_by' => 1],
        ]);
        DB::table('crm_relationships')->insert(['id' => 2, 'hq_id' => 1, 'person_customer_id' => 12, 'company_customer_id' => 13, 'role_title' => 'm', 'created_by' => 1]);

        $this->putJson($this->endpoint(), ['items' => $items])->assertUnprocessable()->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseHas('crm_contact_points', ['id' => 1, 'customer_id' => 11]);
    }

    public function test_an_address_reference_and_a_relationship_of_the_same_person_are_accepted(): void
    {
        $this->authenticate();
        DB::table('crm_customer_address')->insert(['id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'country_code' => 'IR', 'address_text' => 'a', 'purpose' => 'MAIN', 'is_default' => true, 'created_by' => 1]);
        DB::table('crm_relationships')->insert(['id' => 1, 'hq_id' => 1, 'person_customer_id' => 11, 'company_customer_id' => 13, 'role_title' => 'm', 'created_by' => 1]);

        $this->putJson($this->endpoint(), ['items' => [
            $this->item(['type' => 'ADDRESS_REFERENCE', 'identifier_kind' => 'ADDRESS', 'value' => 'home', 'address_id' => 1, 'relationship_id' => 1, 'scope' => 'WORK']),
        ]])->assertOk()
            ->assertJsonPath('data.items.0.address_id', '1')
            ->assertJsonPath('data.items.0.relationship_id', '1')
            ->assertJsonPath('data.items.0.normalized_value', '1');
    }

    public function test_a_mobile_of_another_person_is_a_conflict_and_the_set_is_left_alone(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 1]);
        $this->contactPoint(['id' => 2, 'customer_id' => 12, 'value' => '09129999999', 'normalized_value' => '+989129999999']);

        $this->putJson($this->endpoint(), ['items' => [$this->item(['value' => '0912 999 9999'])]])
            ->assertStatus(409)->assertJsonPath('error_code', 'MOBILE_OWNED_BY_OTHER_PERSON')
            ->assertJsonStructure(['field_errors' => ['items.0.value']]);
        $this->assertDatabaseHas('crm_contact_points', ['id' => 1, 'normalized_value' => '+989120000123']);
    }

    public function test_only_mobile_numbers_are_guarded_and_only_within_the_tenant(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 2, 'customer_id' => 12, 'type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => 'shared@x.com', 'normalized_value' => 'shared@x.com']);
        $this->customer(['id' => 21, 'hq_id' => 2, 'created_by' => 2]);
        $this->contactPoint(['id' => 3, 'hq_id' => 2, 'customer_id' => 21, 'value' => '09128888888', 'normalized_value' => '+989128888888', 'created_by' => 2]);

        $this->putJson($this->endpoint(), ['items' => [
            $this->item(['value' => '09128888888']),
            $this->item(['type' => 'EMAIL', 'identifier_kind' => 'EMAIL', 'value' => 'Shared@x.com']),
        ]])->assertOk();
    }

    public function test_an_inactive_mobile_of_another_person_does_not_hold_the_number(): void
    {
        $this->authenticate();
        $this->contactPoint(['id' => 2, 'customer_id' => 12, 'status' => 'INACTIVE', 'is_default' => false]);

        $this->putJson($this->endpoint(), ['items' => [$this->item()]])->assertOk();
    }

    public function test_a_company_has_no_contact_points_and_foreign_customers_are_missing(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint('13'), ['items' => []])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['customer_id']]);
        $this->putJson($this->endpoint('21'), ['items' => []])->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->getJson($this->endpoint('21'))->assertNotFound();
        $this->getJson($this->endpoint('999'))->assertNotFound();
    }

    public function test_the_read_needs_view_the_write_needs_edit_and_the_module_must_be_enabled(): void
    {
        $this->authenticate(permissions: ['customer.view']);
        $this->getJson($this->endpoint())->assertOk();
        $this->putJson($this->endpoint(), ['items' => []])->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');

        $this->authenticate(permissions: ['customer.edit']);
        $this->getJson($this->endpoint())->assertForbidden();

        $this->authenticate(enabled: false);
        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', 'ENTITLEMENT_DISABLED');
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
            ['id' => 1, 'hq_code' => 'customer-contact-points', 'hq_title' => 'customer-contact-points tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer();
        $this->customer(['id' => 12, 'display_name' => 'Other Person']);
        $this->customer(['id' => 13, 'kind' => 'COMPANY', 'display_name' => 'Acme']);
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
        return '/api/v1/crm/customers/'.$customerId.'/contact-points';
    }

    private function item(array $overrides = []): array
    {
        return array_replace([
            'type' => 'MOBILE', 'identifier_kind' => 'PHONE', 'value' => '0912 000 0123', 'scope' => 'PERSONAL',
            'is_default' => true, 'status' => 'ACTIVE', 'priority' => 1,
        ], $overrides);
    }

    private function contactPoint(array $attributes = []): void
    {
        DB::table('crm_contact_points')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'type' => 'MOBILE', 'identifier_kind' => 'PHONE',
            'value' => '09120000123', 'normalized_value' => '+989120000123', 'scope' => 'PERSONAL',
            'is_default' => true, 'status' => 'ACTIVE', 'created_by' => 1, 'created_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit'],
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-contact-points-session', $hqId, false, 'customer-contact-points-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-contact-points')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope(ScopeType::TENANT, null)]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-contact-points');

        return $principal;
    }
}
