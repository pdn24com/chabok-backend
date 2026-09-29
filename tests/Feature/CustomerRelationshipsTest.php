<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
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

final class CustomerRelationshipsTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'provinces', 'cities', 'countries', 'crm_customers', 'crm_customer_address',
        'crm_customer_departments', 'crm_positions', 'crm_relationships', 'crm_contact_points', 'idempotency_records',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    public function test_creating_a_relationship_returns_the_named_item(): void
    {
        $this->authenticate();

        $this->post201(['person_customer_id' => 21, 'position_id' => 1, 'role_title' => 'Buyer', 'decision_level' => 'Senior', 'signing_authority' => 'Up to limit', 'valid_from' => '2026-01-01', 'is_primary' => true])
            ->assertJsonPath('data.relationship_id', '1')
            ->assertJsonPath('data.person', ['customer_id' => '21', 'display_name' => 'Ali Person'])
            ->assertJsonPath('data.company', ['customer_id' => '11', 'display_name' => 'Acme'])
            ->assertJsonPath('data.position', ['position_id' => '1', 'title' => 'Buyer'])
            ->assertJsonPath('data.valid_from', '2026-01-01')
            ->assertJsonPath('data.valid_to', null)
            ->assertJsonPath('data.is_primary', true);
    }

    public function test_the_position_is_optional_and_shows_as_null(): void
    {
        $this->authenticate();

        $this->post201(['person_customer_id' => 21, 'role_title' => 'Buyer'])->assertJsonPath('data.position', null);
    }

    public function test_a_person_cannot_hold_two_open_relationships_with_one_company(): void
    {
        $this->authenticate();
        $this->post201(['person_customer_id' => 21, 'role_title' => 'Buyer']);

        $this->postRelationship(['person_customer_id' => 21, 'role_title' => 'Again'])
            ->assertStatus(409)->assertJsonPath('error_code', 'RELATIONSHIP_ALREADY_EXISTS');
        $this->assertDatabaseCount('crm_relationships', 1);
    }

    public function test_a_person_may_return_after_the_earlier_relationship_ended(): void
    {
        $this->authenticate();
        $this->relationship(['id' => 1, 'person_customer_id' => 21, 'valid_to' => '2020-01-01']);

        $this->post201(['person_customer_id' => 21, 'role_title' => 'Again']);
    }

    public function test_a_second_primary_is_a_conflict_unless_it_replaces_the_first(): void
    {
        $this->authenticate();
        $this->post201(['person_customer_id' => 21, 'role_title' => 'Buyer', 'is_primary' => true]);

        $this->postRelationship(['person_customer_id' => 22, 'role_title' => 'CEO', 'is_primary' => true])
            ->assertStatus(409)->assertJsonPath('error_code', 'PRIMARY_RELATIONSHIP_EXISTS');
        $this->assertDatabaseCount('crm_relationships', 1);

        $this->post201(['person_customer_id' => 22, 'role_title' => 'CEO', 'is_primary' => true, 'replace_primary' => true]);
        $this->assertDatabaseHas('crm_relationships', ['id' => 1, 'is_primary' => false]);
        $this->assertDatabaseHas('crm_relationships', ['id' => 2, 'is_primary' => true]);
        self::assertSame(1, DB::table('crm_relationships')->where('is_primary', true)->count());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidBodies(): array
    {
        return [
            'the end before the start' => [['person_customer_id' => 23, 'role_title' => 'x', 'valid_from' => '2026-05-01', 'valid_to' => '2026-04-01'], 'valid_to'],
            'a position of another company' => [['person_customer_id' => 23, 'role_title' => 'x', 'position_id' => 2], 'position_id'],
            'a company as the person' => [['person_customer_id' => 12, 'role_title' => 'x'], 'person_customer_id'],
            'a person of another tenant' => [['person_customer_id' => 31, 'role_title' => 'x'], 'person_customer_id'],
            'neither person_customer_id nor new_person' => [['role_title' => 'x'], 'person_customer_id'],
            'both person_customer_id and new_person' => [['person_customer_id' => 23, 'new_person' => ['first_name' => 'a', 'family_name' => 'b', 'mobile' => '09121234567'], 'role_title' => 'x'], 'person_customer_id'],
            'a missing role title' => [['person_customer_id' => 23], 'role_title'],
            'a date in another format' => [['person_customer_id' => 23, 'role_title' => 'x', 'valid_from' => '01/02/2026'], 'valid_from'],
            'an ended relationship as primary' => [['person_customer_id' => 23, 'role_title' => 'x', 'is_primary' => true, 'valid_to' => '2020-01-01'], 'is_primary'],
            'an unusable mobile for a new person' => [['new_person' => ['first_name' => 'a', 'family_name' => 'b', 'mobile' => '123'], 'role_title' => 'x'], 'new_person.mobile'],
            'a new person without a family name' => [['new_person' => ['first_name' => 'a', 'mobile' => '09121234567'], 'role_title' => 'x'], 'new_person.family_name'],
        ];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('invalidBodies')]
    public function test_an_invalid_body_is_refused_and_nothing_is_written(array $body, string $field): void
    {
        $this->authenticate();

        $this->postRelationship($body)->assertUnprocessable()->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_relationships', 0);
    }

    public function test_a_relationship_is_created_from_a_company_of_the_tenant_only(): void
    {
        $this->authenticate();

        $this->postRelationship(['person_customer_id' => 23, 'role_title' => 'x'], '21')->assertUnprocessable()->assertJsonStructure(['field_errors' => ['customer_id']]);
        $this->postRelationship(['person_customer_id' => 23, 'role_title' => 'x'], '32')->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->postRelationship(['person_customer_id' => 23, 'role_title' => 'x'], '999')->assertNotFound();
    }

    public function test_a_new_person_is_created_with_their_mobile_and_the_relationship_in_one_write(): void
    {
        $this->authenticate();

        $response = $this->post201(['new_person' => ['first_name' => 'Sara', 'family_name' => 'Rad', 'mobile' => '0912 111 2233'], 'role_title' => 'Accountant', 'position_id' => 1])
            ->assertJsonPath('data.person.display_name', 'Sara Rad');

        $personId = $response->json('data.person.customer_id');
        $this->assertDatabaseHas('crm_customers', [
            'id' => $personId, 'kind' => 'PERSON', 'phase' => 'LEAD', 'lifecycle' => 'ACTIVE', 'assignee_id' => 1,
            'created_by' => 1, 'first_name' => 'Sara', 'family_name' => 'Rad', 'display_name' => 'Sara Rad',
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'customer_id' => $personId, 'type' => 'MOBILE', 'scope' => 'WORK', 'is_default' => true,
            'normalized_value' => '+989121112233', 'relationship_id' => $response->json('data.relationship_id'),
        ]);
    }

    public function test_a_new_person_of_a_customer_company_starts_as_a_customer(): void
    {
        $this->authenticate();
        $this->customer(['id' => 14, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER', 'display_name' => 'Big Co']);

        $response = $this->postRelationship(['new_person' => ['first_name' => 'Sara', 'family_name' => 'Rad', 'mobile' => '09121112233'], 'role_title' => 'x'], '14')->assertCreated();

        $this->assertDatabaseHas('crm_customers', ['id' => $response->json('data.person.customer_id'), 'phase' => 'CUSTOMER']);
        self::assertNotNull(DB::table('crm_customers')->where('id', $response->json('data.person.customer_id'))->value('converted_at'));
    }

    public function test_a_mobile_owned_by_another_person_refuses_everything_including_the_primary_replacement(): void
    {
        $this->authenticate();
        $this->relationship(['id' => 1, 'person_customer_id' => 21, 'is_primary' => true]);
        $this->contactPoint(['customer_id' => 23, 'value' => '09125550000', 'normalized_value' => '+989125550000']);
        $customers = DB::table('crm_customers')->count();

        $this->postRelationship(['new_person' => ['first_name' => 'X', 'family_name' => 'Y', 'mobile' => '09125550000'], 'role_title' => 'x', 'is_primary' => true, 'replace_primary' => true])
            ->assertStatus(409)->assertJsonPath('error_code', 'MOBILE_OWNED_BY_OTHER_PERSON')
            ->assertJsonStructure(['field_errors' => ['new_person.mobile']]);

        self::assertSame($customers, DB::table('crm_customers')->count());
        $this->assertDatabaseCount('crm_relationships', 1);
        $this->assertDatabaseCount('crm_contact_points', 1);
        $this->assertDatabaseHas('crm_relationships', ['id' => 1, 'is_primary' => true]);
    }

    public function test_a_repeated_request_with_the_same_key_creates_the_person_once(): void
    {
        $this->authenticate();
        $body = ['new_person' => ['first_name' => 'Rep', 'family_name' => 'Lay', 'mobile' => '09127770000'], 'role_title' => 'x'];
        $customers = DB::table('crm_customers')->count();

        $first = $this->withHeader('Idempotency-Key', 'relationship-replay-0001')->postJson($this->endpoint(), $body)->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'relationship-replay-0001')->postJson($this->endpoint(), $body)->assertCreated();

        self::assertSame($first->json('data'), $second->json('data'));
        self::assertSame($customers + 1, DB::table('crm_customers')->count());
        $this->assertDatabaseCount('crm_relationships', 1);
    }

    public function test_the_key_is_required(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint(), ['person_customer_id' => 21, 'role_title' => 'x'])->assertUnprocessable();
    }

    public function test_the_list_works_from_both_ends_with_the_primary_first_and_names_loaded_in_batch(): void
    {
        $this->authenticate();
        $this->relationship(['id' => 1, 'person_customer_id' => 21, 'position_id' => 1]);
        $this->relationship(['id' => 2, 'person_customer_id' => 22, 'is_primary' => true]);
        $this->relationship(['id' => 3, 'person_customer_id' => 23]);

        $fromCompany = $this->getJson($this->endpoint())->assertOk();
        self::assertSame(['2', '3', '1'], array_column($fromCompany->json('data.items'), 'relationship_id'));
        $fromCompany->assertJsonPath('data.items.2.position', ['position_id' => '1', 'title' => 'Buyer'])
            ->assertJsonPath('data.items.0.person.display_name', 'Bita Person');

        $fromPerson = $this->getJson($this->endpoint('21'))->assertOk()->assertJsonCount(1, 'data.items');
        $fromPerson->assertJsonPath('data.items.0.company.display_name', 'Acme');
    }

    public function test_active_only_leaves_out_ended_and_not_yet_started_relationships(): void
    {
        $this->authenticate();
        $today = now()->format('Y-m-d');
        $this->relationship(['id' => 1, 'person_customer_id' => 21]);
        $this->relationship(['id' => 2, 'person_customer_id' => 22, 'valid_to' => '2020-01-01']);
        $this->relationship(['id' => 3, 'person_customer_id' => 23, 'valid_to' => $today]);
        $this->customer(['id' => 24, 'display_name' => 'Future Person']);
        $this->relationship(['id' => 4, 'person_customer_id' => 24, 'valid_from' => now()->addDays(30)->format('Y-m-d')]);

        $active = $this->getJson($this->endpoint().'?active=true')->assertOk();
        self::assertEqualsCanonicalizing(['1', '3'], array_column($active->json('data.items'), 'relationship_id'));

        $this->getJson($this->endpoint().'?active=false')->assertOk()->assertJsonCount(4, 'data.items');
        $this->getJson($this->endpoint())->assertOk()->assertJsonCount(4, 'data.items');
    }

    public function test_ending_sets_the_date_and_releases_the_primary_slot(): void
    {
        $this->authenticate();
        $this->relationship(['id' => 1, 'person_customer_id' => 21, 'is_primary' => true, 'valid_from' => '2026-01-01']);

        $this->postJson('/api/v1/crm/relationships/1/end', ['valid_to' => '2026-09-01'])->assertOk()
            ->assertJsonPath('data.relationship_id', '1')
            ->assertJsonPath('data.valid_to', '2026-09-01')
            ->assertJsonPath('data.is_primary', false);

        // The slot is free again for another primary.
        $this->post201(['person_customer_id' => 22, 'role_title' => 'CEO', 'is_primary' => true]);
    }

    public function test_ending_needs_a_date_that_is_not_before_the_start_and_not_already_past(): void
    {
        $this->authenticate();
        $this->relationship(['id' => 1, 'person_customer_id' => 21, 'valid_from' => '2026-01-01']);
        $this->relationship(['id' => 2, 'person_customer_id' => 22, 'valid_to' => '2020-01-01']);

        $this->postJson('/api/v1/crm/relationships/1/end', [])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['valid_to']]);
        $this->postJson('/api/v1/crm/relationships/1/end', ['valid_to' => '2025-12-31'])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['valid_to']]);
        $this->postJson('/api/v1/crm/relationships/2/end', ['valid_to' => now()->format('Y-m-d')])
            ->assertStatus(409)->assertJsonPath('error_code', 'RELATIONSHIP_ALREADY_ENDED');
        $this->assertDatabaseHas('crm_relationships', ['id' => 2, 'is_primary' => false]);
    }

    public function test_a_relationship_ending_today_can_still_be_re_dated(): void
    {
        $this->authenticate();
        $this->relationship(['id' => 1, 'person_customer_id' => 21, 'valid_to' => now()->format('Y-m-d')]);

        $this->postJson('/api/v1/crm/relationships/1/end', ['valid_to' => now()->addDays(3)->format('Y-m-d')])->assertOk();
    }

    public function test_foreign_and_unknown_relationships_and_customers_are_missing(): void
    {
        $this->authenticate();
        $this->relationship(['id' => 50, 'hq_id' => 2, 'person_customer_id' => 31, 'company_customer_id' => 32, 'created_by' => 2]);

        $this->postJson('/api/v1/crm/relationships/50/end', ['valid_to' => '2026-09-01'])->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->postJson('/api/v1/crm/relationships/999/end', ['valid_to' => '2026-09-01'])->assertNotFound();
        $this->getJson($this->endpoint('32'))->assertNotFound();
        $this->getJson($this->endpoint('999'))->assertNotFound();
    }

    public function test_the_read_needs_view_the_writes_need_edit_and_the_module_must_be_enabled(): void
    {
        $this->relationship(['id' => 1, 'person_customer_id' => 21]);

        $this->authenticate(permissions: ['customer.view']);
        $this->getJson($this->endpoint())->assertOk();
        $this->postRelationship(['person_customer_id' => 22, 'role_title' => 'x'])->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->postJson('/api/v1/crm/relationships/1/end', ['valid_to' => '2026-09-01'])->assertForbidden();

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
            ['id' => 1, 'hq_code' => 'customer-relationships', 'hq_title' => 'customer-relationships tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer(['id' => 11, 'kind' => 'COMPANY', 'display_name' => 'Acme', 'assignee_id' => 1]);
        $this->customer(['id' => 12, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER', 'display_name' => 'Other Co']);
        $this->customer(['id' => 21, 'display_name' => 'Ali Person']);
        $this->customer(['id' => 22, 'display_name' => 'Bita Person']);
        $this->customer(['id' => 23, 'display_name' => 'Cyrus Person']);
        $this->customer(['id' => 31, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'Foreign Person']);
        $this->customer(['id' => 32, 'hq_id' => 2, 'created_by' => 2, 'kind' => 'COMPANY', 'display_name' => 'Foreign Co']);
        DB::table('crm_customer_departments')->insert([
            ['id' => 1, 'hq_id' => 1, 'company_customer_id' => 11, 'title' => 'Purchasing', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'company_customer_id' => 12, 'title' => 'Other', 'created_by' => 1],
        ]);
        DB::table('crm_positions')->insert([
            ['id' => 1, 'hq_id' => 1, 'department_id' => 1, 'title' => 'Buyer', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 1, 'department_id' => 2, 'title' => 'Foreign post', 'created_by' => 1],
        ]);
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
        return '/api/v1/crm/customers/'.$customerId.'/relationships';
    }

    private function postRelationship(array $body, string $customerId = '11'): TestResponse
    {
        static $sequence = 0;

        return $this->withHeader('Idempotency-Key', 'relationship-test-'.str_pad((string) ++$sequence, 6, '0', STR_PAD_LEFT))
            ->postJson($this->endpoint($customerId), $body);
    }

    private function post201(array $body): TestResponse
    {
        return $this->postRelationship($body)->assertCreated();
    }

    private function relationship(array $attributes = []): void
    {
        DB::table('crm_relationships')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'person_customer_id' => 21, 'company_customer_id' => 11, 'position_id' => null,
            'role_title' => 'Buyer', 'is_primary' => false, 'created_by' => 1, 'created_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function contactPoint(array $attributes = []): void
    {
        DB::table('crm_contact_points')->insert(array_replace([
            'hq_id' => 1, 'customer_id' => 21, 'type' => 'MOBILE', 'identifier_kind' => 'PHONE',
            'value' => '09120000123', 'normalized_value' => '+989120000123', 'scope' => 'PERSONAL',
            'is_default' => true, 'status' => 'ACTIVE', 'created_by' => 1, 'created_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }


    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit'],
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-relationships-session', $hqId, false, 'customer-relationships-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-relationships')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope(ScopeType::TENANT, null)]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-relationships');

        return $principal;
    }
}
