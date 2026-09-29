<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Modules\Customer\Application\Dto\CustomerAddressDto;
use Modules\Customer\Application\Dto\CustomerDraftDto;
use Modules\Customer\Application\UseCases\CreateCustomer\CreateCustomerCommand;
use Modules\Customer\Application\UseCases\CreateCustomer\CreateCustomerHandler;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;
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
use Modules\Geography\Infrastructure\Database\Seeders\CountrySeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CreateCustomerTest extends TestCase
{
    private const ENDPOINT = '/api/v1/customers';

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    #[DataProvider('customerTypes')]
    public function test_customer_and_default_address_are_created_without_an_assignee(string $kind, string $phase): void
    {
        $this->authenticate();
        $response = $this->postJson(self::ENDPOINT, $this->payload(['kind' => $kind, 'phase' => $phase]))
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'علی')
            ->assertJsonPath('data.family_name', 'احمدی')
            ->assertJsonPath('data.display_name', 'علی احمدی')
            ->assertJsonPath('data.customer_code', null)
            ->assertJsonPath('data.kind', $kind)
            ->assertJsonPath('data.phase', $phase)
            ->assertJsonPath('data.lifecycle', 'ACTIVE')
            ->assertJsonPath('data.mobile', '09121234567')
            ->assertJsonPath('data.industry_id', null)
            ->assertJsonPath('data.assignee_id', null)
            ->assertJsonPath('data.hq_id', '1')
            ->assertJsonPath('data.created_by', '1')
            ->assertJsonPath('data.address.country_code', 'IR')
            ->assertJsonPath('data.address.province_id', '1')
            ->assertJsonPath('data.address.city_id', '1')
            ->assertJsonPath('data.address.foreign_city', null)
            ->assertJsonPath('data.address.postal_code', null)
            ->assertJsonPath('data.address.address_text', null)
            ->assertJsonPath('data.address.purpose', 'MAIN')
            ->assertJsonPath('data.address.is_default', true)
            ->assertJsonMissingPath('data.id')
            ->assertJsonStructure(['data' => ['customer_id', 'created_at', 'updated_at', 'address' => ['customer_address_id']], 'meta', 'correlation_id']);

        self::assertMatchesRegularExpression('/^[1-9][0-9]*$/', $response->json('data.customer_id'));
        if ($phase === 'LEAD') {
            $response->assertJsonPath('data.converted_at', null);
        } else {
            self::assertNotNull($response->json('data.converted_at'));
        }
        $this->assertDatabaseCount('crm_customers', 1);
        $this->assertDatabaseCount('crm_customer_address', 1);
        $this->assertDatabaseCount('crm_contact_points', 1);
        $this->assertDatabaseCount('crm_customer_industry', 0);
        $this->assertDatabaseHas('crm_customers', [
            'id' => $response->json('data.customer_id'), 'hq_id' => 1, 'created_by' => 1,
            'kind' => $kind, 'phase' => $phase, 'first_name' => 'علی', 'family_name' => 'احمدی',
            'display_name' => 'علی احمدی', 'assignee_id' => null, 'customer_code' => null,
        ]);
        $this->assertDatabaseHas('crm_customer_address', [
            'id' => $response->json('data.address.customer_address_id'), 'customer_id' => $response->json('data.customer_id'),
            'hq_id' => 1, 'created_by' => 1, 'country_code' => 'IR', 'province_id' => 1, 'city_id' => 1,
            'is_default' => true, 'purpose' => 'MAIN', 'address_text' => null, 'postal_code' => null,
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'customer_id' => $response->json('data.customer_id'), 'hq_id' => 1, 'created_by' => 1,
            'type' => 'MOBILE', 'value' => '09121234567', 'normalized_value' => '+989121234567',
            'scope' => $kind === 'COMPANY' ? 'WORK' : 'PERSONAL', 'identifier_kind' => 'PHONE',
            'is_default' => true, 'status' => 'ACTIVE', 'relationship_id' => null, 'address_id' => null,
        ]);
        $customer = CustomerRecord::query()->with('defaultAddress')->sole();
        self::assertSame($response->json('data.address.customer_address_id'), $customer->defaultAddress->customer_address_id);
    }

    public static function customerTypes(): array
    {
        return [['PERSON', 'LEAD'], ['PERSON', 'CUSTOMER'], ['COMPANY', 'LEAD'], ['COMPANY', 'CUSTOMER']];
    }

    public function test_industry_assignee_and_address_details_are_saved(): void
    {
        $this->authenticate();
        $response = $this->postJson(self::ENDPOINT, $this->payload([
            'industry_id' => 1, 'assignee_id' => 1, 'postal_code' => '1234567890',
            'address_text' => 'تهران، خیابان ولیعصر، پلاک ۱۰',
        ]))->assertCreated()
            ->assertJsonPath('data.industry_id', '1')
            ->assertJsonPath('data.assignee_id', '1')
            ->assertJsonPath('data.address.postal_code', '1234567890')
            ->assertJsonPath('data.address.address_text', 'تهران، خیابان ولیعصر، پلاک ۱۰');

        $this->assertDatabaseHas('crm_customers', ['id' => $response->json('data.customer_id'), 'assignee_id' => 1]);
        $this->assertDatabaseHas('crm_customer_industry', [
            'customer_id' => $response->json('data.customer_id'), 'hq_id' => 1,
            'industry_id' => 1, 'is_primary' => true, 'created_by' => 1,
        ]);
        $this->assertDatabaseHas('crm_customer_address', [
            'customer_id' => $response->json('data.customer_id'),
            'postal_code' => '1234567890', 'address_text' => 'تهران، خیابان ولیعصر، پلاک ۱۰',
        ]);
    }

    #[DataProvider('mobileNumbers')]
    public function test_mobile_is_kept_as_typed_next_to_its_canonical_form(string $entered, string $stored, string $normalized): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload(['mobile' => $entered]))
            ->assertCreated()->assertJsonPath('data.mobile', $stored);
        $this->assertDatabaseHas('crm_contact_points', ['value' => $stored, 'normalized_value' => $normalized]);
    }

    public static function mobileNumbers(): array
    {
        return [
            'local form' => ['09121234567', '09121234567', '+989121234567'],
            'without the leading zero' => ['9121234567', '9121234567', '+989121234567'],
            'grouped digits' => ['  0912 123 4567 ', '0912 123 4567', '+989121234567'],
            'separated by dashes' => ['0912-123-4567', '0912-123-4567', '+989121234567'],
            'persian digits' => ['۰۹۱۲۱۲۳۴۵۶۷', '۰۹۱۲۱۲۳۴۵۶۷', '+989121234567'],
            'international form' => ['+98 912 123 4567', '+98 912 123 4567', '+989121234567'],
            'double zero prefix' => ['00989121234567', '00989121234567', '+989121234567'],
            'number of another country' => ['+971501234567', '+971501234567', '+971501234567'],
        ];
    }

    public function test_the_same_mobile_number_may_belong_to_more_than_one_record(): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $this->withHeader('Idempotency-Key', 'shared-mobile-number-0002')
            ->postJson(self::ENDPOINT, $this->payload(['display_name' => 'علی احمدی دوم']))->assertCreated();

        $this->assertDatabaseCount('crm_customers', 2);
        $this->assertDatabaseCount('crm_contact_points', 2);
    }

    public function test_customer_code_is_saved_as_text_including_leading_zeros(): void
    {
        $this->authenticate();
        $response = $this->postJson(self::ENDPOINT, $this->payload(['customer_code' => '000123']))
            ->assertCreated()->assertJsonPath('data.customer_code', '000123');
        $this->assertDatabaseHas('crm_customers', ['id' => $response->json('data.customer_id'), 'customer_code' => '000123']);
    }

    public function test_customer_codes_are_unique_within_each_tenant(): void
    {
        $this->authenticate();
        DB::table('crm_customers')->insert([
            'hq_id' => 2, 'created_by' => 2, 'kind' => 'PERSON', 'phase' => 'LEAD', 'customer_code' => 'C-001',
        ]);

        $this->postJson(self::ENDPOINT, $this->payload(['customer_code' => 'C-001']))
            ->assertCreated()->assertJsonPath('data.customer_code', 'C-001');
        $this->withHeader('Idempotency-Key', 'duplicate-customer-code-0002')
            ->postJson(self::ENDPOINT, $this->payload(['customer_code' => 'C-001']))
            ->assertConflict()->assertJsonPath('error_code', 'CONFLICT');
        $this->assertDatabaseCount('crm_customers', 2);
        $this->assertDatabaseCount('crm_customer_address', 1);
        $this->assertDatabaseCount('idempotency_records', 1);
    }

    public function test_empty_and_null_customer_codes_allow_multiple_customers(): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload(['customer_code' => '']))
            ->assertCreated()->assertJsonPath('data.customer_code', null);
        $this->withHeader('Idempotency-Key', 'empty-customer-code-0002')
            ->postJson(self::ENDPOINT, $this->payload(['customer_code' => null]))
            ->assertCreated()->assertJsonPath('data.customer_code', null);
        $this->assertDatabaseCount('crm_customers', 2);
    }

    public function test_foreign_customer_uses_a_city_name_without_iranian_city_or_province(): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload(['country_code' => 'ae', 'province_id' => null, 'city_id' => null, 'foreign_city' => 'دبی']))
            ->assertCreated()
            ->assertJsonPath('data.address.country_code', 'AE')
            ->assertJsonPath('data.address.province_id', null)
            ->assertJsonPath('data.address.city_id', null)
            ->assertJsonPath('data.address.foreign_city', 'دبی');
        $this->assertDatabaseHas('crm_customer_address', ['country_code' => 'AE', 'foreign_city' => 'دبی', 'city_id' => null, 'province_id' => null]);
    }

    public function test_selected_province_and_city_are_saved_from_string_identifiers(): void
    {
        $this->authenticate();
        DB::table('cities')->insert(['id' => 2, 'province_id' => 2, 'legacy_city_code' => '002', 'name_fa' => 'شیراز', 'normalized_name' => 'شیراز']);

        $this->postJson(self::ENDPOINT, $this->payload(['province_id' => '2', 'city_id' => '2']))
            ->assertCreated()
            ->assertJsonPath('data.address.province_id', '2')
            ->assertJsonPath('data.address.city_id', '2');
        $this->assertDatabaseHas('crm_customer_address', ['province_id' => 2, 'city_id' => 2]);
    }

    public function test_province_must_be_supplied_for_iran(): void
    {
        $this->authenticate();
        $payload = $this->payload();
        unset($payload['province_id']);

        $this->postJson(self::ENDPOINT, $payload)->assertUnprocessable()->assertJsonStructure(['field_errors' => ['province_id']]);
        $this->assertDatabaseCount('crm_customers', 0);
        $this->assertDatabaseCount('crm_customer_address', 0);
    }

    public function test_ownership_and_assignment_cannot_be_overridden_by_request_fields(): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload([
            'hq_id' => 2, 'created_by' => 2, 'lifecycle' => 'ARCHIVED',
            'is_default' => false, 'purpose' => 'OTHER',
        ]))->assertCreated()
            ->assertJsonPath('data.hq_id', '1')
            ->assertJsonPath('data.created_by', '1')
            ->assertJsonPath('data.assignee_id', null)
            ->assertJsonPath('data.lifecycle', 'ACTIVE')
            ->assertJsonPath('data.address.province_id', '1')
            ->assertJsonPath('data.address.is_default', true);
        $this->assertDatabaseHas('crm_customer_address', ['hq_id' => 1, 'created_by' => 1]);
        $this->assertDatabaseMissing('crm_customers', ['hq_id' => 2]);
    }

    public function test_request_replay_returns_the_same_customer_without_duplicate_rows(): void
    {
        $this->authenticate();
        $first = $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()->assertJsonPath('data', $first->json('data'));
        $this->postJson(self::ENDPOINT, $this->payload(['display_name' => 'نام دیگر']))
            ->assertConflict()->assertJsonPath('error_code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertDatabaseCount('crm_customers', 1);
        $this->assertDatabaseCount('crm_customer_address', 1);
    }

    public function test_a_failure_writing_the_address_rolls_back_the_customer_and_idempotency_record(): void
    {
        $this->authenticate();
        DB::statement("CREATE TRIGGER reject_customer_address BEFORE INSERT ON crm_customer_address BEGIN SELECT RAISE(ABORT, 'Address write failed'); END");
        $this->postJson(self::ENDPOINT, $this->payload())->assertConflict();
        $this->assertDatabaseCount('crm_customers', 0);
        $this->assertDatabaseCount('crm_customer_address', 0);
        $this->assertDatabaseCount('crm_contact_points', 0);
        $this->assertDatabaseCount('idempotency_records', 0);

        DB::statement('DROP TRIGGER reject_customer_address');
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_inputs_are_rejected_without_writing_either_record(array $changes, string $field): void
    {
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload($changes))->assertUnprocessable()
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_customers', 0);
        $this->assertDatabaseCount('crm_customer_address', 0);
    }

    public static function invalidInputs(): array
    {
        return [
            'missing first name' => [['first_name' => null], 'first_name'],
            'missing family name' => [['family_name' => null], 'family_name'],
            'empty display name' => [['display_name' => '  '], 'display_name'],
            'long first name' => [['first_name' => str_repeat('ا', 121)], 'first_name'],
            'long family name' => [['family_name' => str_repeat('ا', 121)], 'family_name'],
            'long display name' => [['display_name' => str_repeat('ا', 201)], 'display_name'],
            'invalid name type' => [['first_name' => ['علی']], 'first_name'],
            'long customer code' => [['customer_code' => str_repeat('A', 81)], 'customer_code'],
            'numeric customer code' => [['customer_code' => 123], 'customer_code'],
            'array customer code' => [['customer_code' => ['C-001']], 'customer_code'],
            'invalid kind' => [['kind' => 'OTHER'], 'kind'],
            'missing mobile' => [['mobile' => null], 'mobile'],
            'array mobile' => [['mobile' => ['09121234567']], 'mobile'],
            'long mobile' => [['mobile' => str_repeat('9', 33)], 'mobile'],
            'short mobile' => [['mobile' => '0912123456'], 'mobile'],
            'landline instead of mobile' => [['mobile' => '02112345678'], 'mobile'],
            'lettered mobile' => [['mobile' => '0912ABC4567'], 'mobile'],
            'zero industry' => [['industry_id' => 0], 'industry_id'],
            'non-numeric industry' => [['industry_id' => 'خرده‌فروشی'], 'industry_id'],
            'unknown industry' => [['industry_id' => 999], 'industry_id'],
            'industry of another tenant' => [['industry_id' => 2], 'industry_id'],
            'inactive industry' => [['industry_id' => 3], 'industry_id'],
            'zero assignee' => [['assignee_id' => 0], 'assignee_id'],
            'unknown assignee' => [['assignee_id' => 999], 'assignee_id'],
            'assignee of another tenant' => [['assignee_id' => 2], 'assignee_id'],
            'assignee that is not active' => [['assignee_id' => 3], 'assignee_id'],
            'short postal code' => [['postal_code' => '12345'], 'postal_code'],
            'lettered postal code' => [['postal_code' => 'ABCDEFGHIJ'], 'postal_code'],
            'long address text' => [['address_text' => str_repeat('ا', 1001)], 'address_text'],
            'invalid phase' => [['phase' => 'OTHER'], 'phase'],
            'missing kind' => [['kind' => null], 'kind'],
            'missing phase' => [['phase' => null], 'phase'],
            'missing country' => [['country_code' => null], 'country_code'],
            'invalid country type' => [['country_code' => ['IR']], 'country_code'],
            'unknown country' => [['country_code' => 'ZZ', 'province_id' => null, 'city_id' => null, 'foreign_city' => 'Unknown'], 'country_code'],
            'missing province' => [['province_id' => null], 'province_id'],
            'non-numeric province' => [['province_id' => 'تهران'], 'province_id'],
            'zero province' => [['province_id' => 0], 'province_id'],
            'out of range province' => [['province_id' => 4294967296], 'province_id'],
            'unknown province' => [['province_id' => 999], 'province_id'],
            'city in another province' => [['province_id' => 2], 'province_id'],
            'missing city' => [['city_id' => null], 'city_id'],
            'non-numeric city' => [['city_id' => 'تهران'], 'city_id'],
            'zero city' => [['city_id' => 0], 'city_id'],
            'out of range city' => [['city_id' => 4294967296], 'city_id'],
            'unknown city' => [['city_id' => 999], 'city_id'],
            'foreign city for Iran' => [['foreign_city' => 'دبی'], 'foreign_city'],
            'Iran province for another country' => [['country_code' => 'AE', 'city_id' => null, 'foreign_city' => 'دبی'], 'province_id'],
            'Iran city for another country' => [['country_code' => 'AE', 'province_id' => null, 'foreign_city' => 'دبی'], 'city_id'],
            'missing foreign city' => [['country_code' => 'AE', 'province_id' => null, 'city_id' => null], 'foreign_city'],
            'long foreign city' => [['country_code' => 'AE', 'province_id' => null, 'city_id' => null, 'foreign_city' => str_repeat('ا', 201)], 'foreign_city'],
        ];
    }

    #[DataProvider('inactiveReferences')]
    public function test_inactive_geographic_references_are_not_selectable(string $table, string $field): void
    {
        $this->authenticate();
        DB::table($table)->update(['is_active' => false]);
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnprocessable()->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_customers', 0);
        $this->assertDatabaseCount('crm_customer_address', 0);
    }

    public static function inactiveReferences(): array
    {
        return [['countries', 'country_code'], ['cities', 'city_id'], ['provinces', 'province_id']];
    }

    #[DataProvider('invalidUseCaseAddresses')]
    public function test_address_validation_is_enforced_without_an_http_request(array $changes, ?string $inactiveTable, string $field): void
    {
        $actor = $this->authenticate();
        if ($inactiveTable !== null) {
            DB::table($inactiveTable)->update(['is_active' => false]);
        }
        $address = new CustomerAddressDto(...array_replace([
            'countryCode' => 'IR', 'provinceId' => '1', 'cityId' => '1',
        ], $changes));
        $command = new CreateCustomerCommand($actor, new CustomerDraftDto(
            firstName: 'علی', familyName: 'احمدی', displayName: 'علی احمدی',
            kind: CustomerKind::PERSON, phase: CustomerPhase::LEAD, address: $address, mobile: '09121234567',
        ));

        try {
            $this->app->make(CreateCustomerHandler::class)->handle($command);
            self::fail('An invalid address must be rejected even when FormRequest is not involved.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
            self::assertSame(422, $exception->httpStatus);
            self::assertArrayHasKey($field, $exception->fieldErrors);
        }

        $this->assertDatabaseCount('crm_customers', 0);
        $this->assertDatabaseCount('crm_customer_address', 0);
        self::assertSame(0, DB::connection()->transactionLevel());
    }

    public static function invalidUseCaseAddresses(): array
    {
        return [
            'unknown country' => [['countryCode' => 'ZZ'], null, 'country_code'],
            'inactive country' => [[], 'countries', 'country_code'],
            'missing city' => [['cityId' => null], null, 'city_id'],
            'unknown city' => [['cityId' => '999'], null, 'city_id'],
            'inactive city' => [[], 'cities', 'city_id'],
            'missing province' => [['provinceId' => null], null, 'province_id'],
            'mismatched province' => [['provinceId' => '2'], null, 'province_id'],
            'inactive province' => [[], 'provinces', 'province_id'],
            'foreign city in Iran' => [['foreignCity' => 'دبی'], null, 'foreign_city'],
            'Iranian province abroad' => [['countryCode' => 'AE', 'cityId' => null, 'foreignCity' => 'دبی'], null, 'province_id'],
            'Iranian city abroad' => [['countryCode' => 'AE', 'provinceId' => null, 'foreignCity' => 'دبی'], null, 'city_id'],
            'missing foreign city' => [['countryCode' => 'AE', 'provinceId' => null, 'cityId' => null], null, 'foreign_city'],
            'blank foreign city' => [['countryCode' => 'AE', 'provinceId' => null, 'cityId' => null, 'foreignCity' => '  '], null, 'foreign_city'],
        ];
    }

    public function test_authentication_and_password_change_are_required(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
        $this->authenticate(mustChangePassword: true);
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden()->assertJsonPath('error_code', 'PASSWORD_CHANGE_REQUIRED');
        $this->assertDatabaseCount('crm_customers', 0);
    }

    #[DataProvider('deniedContexts')]
    public function test_tenant_permission_and_entitlement_checks_prevent_unauthorized_writes(?string $hqId, bool $enabled, bool $permission, ScopeType $scope, string $error): void
    {
        $actor = $this->authenticate(hqId: $hqId, enabled: $enabled, permission: $permission, scope: $scope);
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden()->assertJsonPath('error_code', $error);

        $command = new CreateCustomerCommand($actor, new CustomerDraftDto(
            firstName: 'علی', familyName: 'احمدی', displayName: 'علی احمدی',
            kind: CustomerKind::PERSON, phase: CustomerPhase::LEAD,
            address: new CustomerAddressDto(countryCode: 'ZZ'), mobile: '09121234567',
        ));
        try {
            $this->app->make(CreateCustomerHandler::class)->handle($command);
            self::fail('Direct use-case calls must deny access before validating the address or writing records.');
        } catch (ApiException $exception) {
            self::assertSame($error, $exception->errorCode->value);
            self::assertSame(403, $exception->httpStatus);
        }

        $this->assertDatabaseCount('crm_customers', 0);
        $this->assertDatabaseCount('crm_customer_address', 0);
        self::assertSame(0, DB::connection()->transactionLevel());
    }

    public static function deniedContexts(): array
    {
        return [
            'no tenant' => [null, true, true, ScopeType::TENANT, 'TENANT_ACCESS_DENIED'],
            'disabled module' => ['1', false, true, ScopeType::TENANT, 'ENTITLEMENT_DISABLED'],
            'missing permission' => ['1', true, false, ScopeType::TENANT, 'PERMISSION_DENIED'],
            'node scope' => ['1', true, true, ScopeType::NODE, 'SCOPE_ACCESS_DENIED'],
            'self scope' => ['1', true, true, ScopeType::SelfScope, 'SCOPE_ACCESS_DENIED'],
        ];
    }

    public function test_idempotency_key_is_required(): void
    {
        $this->authenticate();
        $this->withHeader('Idempotency-Key', '')->postJson(self::ENDPOINT, $this->payload())
            ->assertUnprocessable()->assertJsonStructure(['field_errors' => ['Idempotency-Key']]);
        $this->assertDatabaseCount('crm_customers', 0);
    }

    public function test_nullable_migration_preserves_existing_assignments_and_foreign_keys(): void
    {
        $migration = require base_path(self::NULLABLE_MIGRATION);
        $migration->down();
        $id = DB::table('crm_customers')->insertGetId([
            'hq_id' => 1, 'created_by' => 1, 'assignee_id' => 1, 'kind' => 'PERSON', 'phase' => 'LEAD',
        ]);
        $migration->up();
        $this->assertDatabaseHas('crm_customers', ['id' => $id, 'assignee_id' => 1]);
        $this->authenticate();
        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated()->assertJsonPath('data.assignee_id', null);

        $foreignKeys = Schema::getForeignKeys('crm_customers');
        self::assertContains(['assignee_id'], array_column($foreignKeys, 'columns'));
        $this->expectException(QueryException::class);
        DB::table('crm_customers')->where('id', $id)->update(['assignee_id' => 999]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true]);
        DB::purge('sqlite');
        $tables = [
            'hq_tenants', 'users', 'provinces', 'cities', 'countries', 'crm_industries', 'crm_customers',
            'crm_customer_address', 'crm_customer_departments', 'crm_positions', 'crm_relationships',
            'crm_contact_points', 'crm_customer_industry', 'idempotency_records',
        ];
        foreach ($tables as $table) {
            (require glob(base_path('Modules/*/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require base_path(self::NULLABLE_MIGRATION))->up();
        $this->seed(CountrySeeder::class);
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CUSTOMER-TEST', 'hq_title' => 'Customer test tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
            ['id' => 3, 'hq_id' => 1, 'first_name' => 'Invited', 'last_name' => 'Operator', 'display_name' => 'Invited Operator', 'status' => 'INVITED'],
        ]);
        DB::table('crm_industries')->insert([
            ['id' => 1, 'hq_id' => 1, 'code' => 'RETAIL', 'title' => 'خرده‌فروشی', 'is_active' => true, 'created_by' => 1],
            ['id' => 2, 'hq_id' => 2, 'code' => 'RETAIL', 'title' => 'خرده‌فروشی', 'is_active' => true, 'created_by' => 2],
            ['id' => 3, 'hq_id' => 1, 'code' => 'RETIRED', 'title' => 'صنف بازنشسته', 'is_active' => false, 'created_by' => 1],
        ]);
        DB::table('provinces')->insert([
            ['id' => 1, 'legacy_province_code' => '01', 'name_fa' => 'تهران', 'normalized_name' => 'تهران', 'latitude' => 35.7, 'longitude' => 51.4],
            ['id' => 2, 'legacy_province_code' => '02', 'name_fa' => 'فارس', 'normalized_name' => 'فارس', 'latitude' => 29.6, 'longitude' => 52.5],
        ]);
        DB::table('cities')->insert(['id' => 1, 'province_id' => 1, 'legacy_city_code' => '001', 'name_fa' => 'تهران', 'normalized_name' => 'تهران']);
        $this->withHeader('Idempotency-Key', 'create-customer-test-0001');
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'first_name' => 'علی', 'family_name' => 'احمدی', 'display_name' => 'علی احمدی',
            'kind' => 'PERSON', 'phase' => 'LEAD', 'mobile' => '09121234567',
            'country_code' => 'IR', 'province_id' => 1, 'city_id' => 1,
        ], $changes);
    }

    private function authenticate(?string $hqId = '1', bool $mustChangePassword = false, bool $enabled = true, bool $permission = true, ScopeType $scope = ScopeType::TENANT): AuthenticatedPrincipal
    {
        $claims = new AccessTokenClaims('1', 'customer-test-session', $hqId, $mustChangePassword, 'customer-test-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-writer')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permission ? ['customer.create'] : [],
            permissionScopes: ['customer.create' => [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]],
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-writer');

        return $principal;
    }
}
