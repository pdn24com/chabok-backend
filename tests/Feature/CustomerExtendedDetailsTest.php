<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Modules\Customer\Application\Dto\CustomerExtendedDetailsDto;
use Modules\Customer\Application\UseCases\GetCustomerExtendedDetails\GetCustomerExtendedDetailsCommand;
use Modules\Customer\Application\UseCases\GetCustomerExtendedDetails\GetCustomerExtendedDetailsHandler;
use Modules\Customer\Application\UseCases\SaveCustomerExtendedDetails\SaveCustomerExtendedDetailsCommand;
use Modules\Customer\Application\UseCases\SaveCustomerExtendedDetails\SaveCustomerExtendedDetailsHandler;
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

final class CustomerExtendedDetailsTest extends TestCase
{
    private const TABLE = 'crm_customer_extended_details';

    public function test_an_untouched_customer_answers_with_an_empty_form(): void
    {
        $this->authenticate();

        $this->getJson($this->endpoint())->assertOk()
            ->assertJsonPath('data', [
                'customer_id' => '11',
                'salutation' => null,
                'birth_date' => null,
                'trade_name' => null,
                'legal_form' => null,
                'legal_name' => null,
                'registration_no' => null,
                'registration_date' => null,
                'registration_place' => null,
                'need_summary' => null,
                'updated_at' => null,
            ])
            ->assertJsonStructure(['data', 'meta', 'correlation_id']);
        $this->assertDatabaseCount(self::TABLE, 0);
    }

    public function test_the_first_save_creates_the_row_and_returns_what_the_read_will_serve(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint(), $this->payload())->assertOk()
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.trade_name', 'پارس‌گستر')
            ->assertJsonPath('data.legal_form', 'سهامی خاص')
            ->assertJsonPath('data.legal_name', 'شرکت پارس‌گستر آریا')
            ->assertJsonPath('data.registration_no', '123456')
            ->assertJsonPath('data.registration_date', 1790640000)
            ->assertJsonPath('data.registration_place', 'تهران')
            ->assertJsonPath('data.need_summary', 'نیاز به حمل یخچالی');

        $this->assertDatabaseCount(self::TABLE, 1);
        $this->assertDatabaseHas(self::TABLE, [
            'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1,
            'trade_name' => 'پارس‌گستر', 'legal_form' => 'سهامی خاص', 'legal_name' => 'شرکت پارس‌گستر آریا',
            'registration_no' => '123456', 'registration_date' => '2026-09-29',
            'registration_place' => 'تهران', 'need_summary' => 'نیاز به حمل یخچالی',
        ]);
        $written = $this->getJson($this->endpoint())->assertOk();
        self::assertSame($written->json('data'), $this->getJson($this->endpoint())->json('data'));
        self::assertNotNull($written->json('data.updated_at'));
    }

    public function test_a_second_save_replaces_the_values_in_the_same_row(): void
    {
        $this->authenticate();
        $this->putJson($this->endpoint(), $this->payload())->assertOk();
        $created = DB::table(self::TABLE)->where('customer_id', 11)->first();

        $this->putJson($this->endpoint(), $this->payload(['trade_name' => 'نام تازه', 'registration_place' => 'شیراز']))
            ->assertOk()
            ->assertJsonPath('data.trade_name', 'نام تازه')
            ->assertJsonPath('data.registration_place', 'شیراز');

        $this->assertDatabaseCount(self::TABLE, 1);
        $updated = DB::table(self::TABLE)->where('customer_id', 11)->first();
        self::assertSame($created->id, $updated->id);
        self::assertSame($created->created_by, $updated->created_by);
        self::assertSame($created->created_at, $updated->created_at);
    }

    public function test_a_field_left_out_of_the_request_is_cleared(): void
    {
        $this->authenticate();
        $this->putJson($this->endpoint(), $this->payload())->assertOk();

        $this->putJson($this->endpoint(), ['trade_name' => 'تنها نام تجاری'])->assertOk()
            ->assertJsonPath('data.trade_name', 'تنها نام تجاری')
            ->assertJsonPath('data.legal_form', null)
            ->assertJsonPath('data.legal_name', null)
            ->assertJsonPath('data.registration_no', null)
            ->assertJsonPath('data.registration_date', null)
            ->assertJsonPath('data.registration_place', null)
            ->assertJsonPath('data.need_summary', null);

        $this->assertDatabaseHas(self::TABLE, [
            'customer_id' => 11, 'trade_name' => 'تنها نام تجاری', 'legal_form' => null, 'legal_name' => null,
            'registration_no' => null, 'registration_date' => null, 'registration_place' => null, 'need_summary' => null,
        ]);
    }

    public function test_an_explicit_null_clears_one_field_and_leaves_the_others_as_sent(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint(), $this->payload(['registration_date' => null, 'need_summary' => null]))
            ->assertOk()
            ->assertJsonPath('data.registration_date', null)
            ->assertJsonPath('data.need_summary', null)
            ->assertJsonPath('data.trade_name', 'پارس‌گستر');
    }

    public function test_qualification_columns_are_saved_with_the_extended_details(): void
    {
        $this->authenticate();
        DB::table(self::TABLE)->insert([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1,
            'salutation' => 'جناب', 'birth_date' => '1990-04-01', 'budget' => 500000000, 'budget_known' => true,
            'qualification_result' => 'QUALIFIED', 'evaluated_by' => 1, 'evaluated_at' => '2026-09-01 08:00:00',
        ]);

        $this->putJson($this->endpoint(), $this->payload([
            'budget' => 600000000,
            'budget_known' => false,
            'qualification_result' => 'PENDING',
        ]))->assertOk();

        $this->assertDatabaseHas(self::TABLE, [
            'id' => 1, 'budget' => 600000000, 'budget_known' => false,
            'qualification_result' => 'PENDING', 'evaluated_by' => 1,
            'salutation' => null, 'birth_date' => null, 'trade_name' => 'پارس‌گستر',
        ]);
        $this->assertDatabaseCount(self::TABLE, 1);
    }

    public function test_the_person_fields_are_written_and_cleared_by_this_form_like_the_company_ones(): void
    {
        $this->authenticate();

        // 1990-04-01 at midnight UTC; a person fills these in where a company fills in the registration.
        $this->putJson($this->endpoint(), $this->payload(['salutation' => 'جناب آقای', 'birth_date' => 639014400]))
            ->assertOk()
            ->assertJsonPath('data.salutation', 'جناب آقای')
            ->assertJsonPath('data.birth_date', 639014400);
        $this->assertDatabaseHas(self::TABLE, ['customer_id' => 11, 'salutation' => 'جناب آقای', 'birth_date' => '1990-04-01']);

        $this->putJson($this->endpoint(), $this->payload())->assertOk()
            ->assertJsonPath('data.salutation', null)
            ->assertJsonPath('data.birth_date', null);
    }

    #[DataProvider('registrationTimestamps')]
    public function test_registration_date_travels_as_a_unix_timestamp(int $sent, string $stored, int $returned): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint(), $this->payload(['registration_date' => $sent]))->assertOk()
            ->assertJsonPath('data.registration_date', $returned);
        $this->assertDatabaseHas(self::TABLE, ['customer_id' => 11, 'registration_date' => $stored]);
    }

    public static function registrationTimestamps(): array
    {
        return [
            'midnight utc' => [1790640000, '2026-09-29', 1790640000],
            // Only the calendar day survives a date column, so midday comes back as that day's midnight.
            'midday utc' => [1790683200, '2026-09-29', 1790640000],
            'the epoch itself' => [0, '1970-01-01', 0],
            'before the epoch' => [-2208988800, '1900-01-01', -2208988800],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_are_rejected_without_writing_the_row(array $changes, string $field): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint(), $this->payload($changes))->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount(self::TABLE, 0);
    }

    public static function invalidInputs(): array
    {
        return [
            'long trade name' => [['trade_name' => str_repeat('ا', 201)], 'trade_name'],
            'long legal form' => [['legal_form' => str_repeat('ا', 121)], 'legal_form'],
            'long legal name' => [['legal_name' => str_repeat('ا', 201)], 'legal_name'],
            'long registration number' => [['registration_no' => str_repeat('1', 81)], 'registration_no'],
            'long registration place' => [['registration_place' => str_repeat('ا', 201)], 'registration_place'],
            'long need summary' => [['need_summary' => str_repeat('ا', 2001)], 'need_summary'],
            'long salutation' => [['salutation' => str_repeat('ا', 81)], 'salutation'],
            'formatted birth date' => [['birth_date' => '1990-04-01'], 'birth_date'],
            'birth date out of range' => [['birth_date' => 9999999999], 'birth_date'],
            'array trade name' => [['trade_name' => ['پارس‌گستر']], 'trade_name'],
            'formatted registration date' => [['registration_date' => '2026-09-29'], 'registration_date'],
            'fractional registration date' => [['registration_date' => 1790640000.5], 'registration_date'],
            'registration date out of range' => [['registration_date' => 9999999999], 'registration_date'],
            'registration date before the supported range' => [['registration_date' => -3000000000], 'registration_date'],
        ];
    }

    #[DataProvider('unreachableCustomers')]
    public function test_a_missing_or_foreign_customer_is_not_found(string $customerId): void
    {
        $actor = $this->authenticate();
        DB::table('crm_customers')->insert([
            'id' => 99, 'hq_id' => 2, 'created_by' => 2, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER',
        ]);

        $this->getJson($this->endpoint($customerId))->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->putJson($this->endpoint($customerId), $this->payload())->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->assertDatabaseCount(self::TABLE, 0);

        foreach ([
            fn () => $this->app->make(GetCustomerExtendedDetailsHandler::class)->handle(new GetCustomerExtendedDetailsCommand($actor, $customerId)),
            fn () => $this->app->make(SaveCustomerExtendedDetailsHandler::class)->handle(new SaveCustomerExtendedDetailsCommand($actor, $customerId, new CustomerExtendedDetailsDto(tradeName: 'x'))),
        ] as $call) {
            try {
                $call();
                self::fail('A customer outside the actor tenant must not be reachable through the use case either.');
            } catch (ApiException $exception) {
                self::assertSame(ApiErrorCode::ResourceNotFound, $exception->errorCode);
                self::assertSame(404, $exception->httpStatus);
            }
        }
        self::assertSame(0, DB::connection()->transactionLevel());
    }

    public static function unreachableCustomers(): array
    {
        return ['unknown record' => ['404'], 'record of another tenant' => ['99']];
    }

    public function test_a_non_numeric_identifier_does_not_reach_either_endpoint(): void
    {
        $this->authenticate();
        $this->getJson('/api/v1/crm/customers/abc/extended-details')->assertNotFound();
        $this->putJson('/api/v1/crm/customers/abc/extended-details', $this->payload())->assertNotFound();
    }

    public function test_authentication_and_completed_password_change_are_required(): void
    {
        $this->getJson($this->endpoint())->assertUnauthorized();
        $this->putJson($this->endpoint(), $this->payload())->assertUnauthorized();
        $this->authenticate(mustChangePassword: true);
        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');
        $this->putJson($this->endpoint(), $this->payload())->assertForbidden()->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');
        $this->assertDatabaseCount(self::TABLE, 0);
    }

    public function test_reading_needs_view_and_writing_needs_edit(): void
    {
        $this->authenticate(permissions: ['customer.edit']);
        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->putJson($this->endpoint(), $this->payload())->assertOk();

        $this->authenticate(permissions: ['customer.view']);
        $this->getJson($this->endpoint())->assertOk();
        $this->putJson($this->endpoint(), $this->payload(['trade_name' => 'تلاش بی‌اجازه']))
            ->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->assertDatabaseHas(self::TABLE, ['customer_id' => 11, 'trade_name' => 'پارس‌گستر']);
    }

    #[DataProvider('deniedContexts')]
    public function test_tenant_entitlement_and_scope_checks_guard_both_endpoints(?string $hqId, bool $enabled, ScopeType $scope, string $error): void
    {
        $this->authenticate(hqId: $hqId, enabled: $enabled, scope: $scope);

        $this->getJson($this->endpoint())->assertForbidden()->assertJsonPath('error_code', $error)->assertJsonMissingPath('data');
        $this->putJson($this->endpoint(), $this->payload())->assertForbidden()->assertJsonPath('error_code', $error);
        $this->assertDatabaseCount(self::TABLE, 0);
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
        foreach (['hq_tenants', 'users', 'crm_customers', 'crm_customer_extended_details'] as $table) {
            (require glob(base_path('Modules/*/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require base_path('Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php'))->up();
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CUSTOMER-EXTENDED', 'hq_title' => 'Customer extended tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        DB::table('crm_customers')->insert([
            'id' => 11, 'hq_id' => 1, 'created_by' => 1, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER',
            'lifecycle' => 'ACTIVE', 'display_name' => 'پارس‌گستر آریا', 'customer_code' => 'C-1001',
        ]);
    }

    private function endpoint(string $customerId = '11'): string
    {
        return '/api/v1/crm/customers/'.$customerId.'/extended-details';
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'trade_name' => 'پارس‌گستر',
            'legal_form' => 'سهامی خاص',
            'legal_name' => 'شرکت پارس‌گستر آریا',
            'registration_no' => '123456',
            'registration_date' => 1790640000,
            'registration_place' => 'تهران',
            'need_summary' => 'نیاز به حمل یخچالی',
        ], $changes);
    }

    /** @param list<string> $permissions */
    private function authenticate(
        array $permissions = ['customer.view', 'customer.edit'],
        ?string $hqId = '1',
        bool $mustChangePassword = false,
        bool $enabled = true,
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-extended-session', $hqId, $mustChangePassword, 'customer-extended-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-extended')->andReturn($claims));
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
        $this->withToken('customer-extended');

        return $principal;
    }
}
