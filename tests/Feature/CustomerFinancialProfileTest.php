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

/**
 * The three field sets behind the supplementary-information tab of a customer file: the registration
 * identity, the evaluation and the manual financial summary.
 */
final class CustomerFinancialProfileTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers',
        'crm_customer_extended_details', 'crm_customer_financial_details',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    // 2015-04-01 and 1990-06-15 at midnight UTC, the spelling every date in these payloads travels in.
    private const REGISTERED_ON = 1427846400;

    private const BORN_ON = 645408000;

    public function test_the_registration_form_keeps_the_person_and_the_company_fields_side_by_side(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint('extended-details'), [
            'salutation' => 'جناب آقای',
            'birth_date' => self::BORN_ON,
            'trade_name' => 'آریا',
            'legal_form' => 'سهامی خاص',
            'legal_name' => 'شرکت پارس‌گستر آریا',
            'registration_no' => '123456',
            'registration_date' => self::REGISTERED_ON,
            'registration_place' => 'تهران',
            'need_summary' => 'نیاز به حمل دوره‌ای',
        ])->assertOk()
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.salutation', 'جناب آقای')
            ->assertJsonPath('data.birth_date', self::BORN_ON)
            ->assertJsonPath('data.legal_name', 'شرکت پارس‌گستر آریا')
            ->assertJsonPath('data.registration_date', self::REGISTERED_ON);

        $this->assertDatabaseHas('crm_customer_extended_details', [
            'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1,
            'salutation' => 'جناب آقای', 'birth_date' => '1990-06-15', 'registration_date' => '2015-04-01',
        ]);
    }

    public function test_a_second_save_replaces_the_whole_extended_detail_set(): void
    {
        $this->authenticate();
        $this->putJson($this->endpoint('extended-details'), ['qualification_result' => 'QUALIFIED', 'budget' => 250000000])->assertOk();
        $this->putJson($this->endpoint('extended-details'), ['trade_name' => 'آریا', 'legal_name' => 'پارس‌گستر'])->assertOk();

        // What the second save leaves out is cleared, because a PUT carries the whole set.
        $this->putJson($this->endpoint('extended-details'), ['trade_name' => 'آریا'])->assertOk()
            ->assertJsonPath('data.trade_name', 'آریا')
            ->assertJsonPath('data.legal_name', null)
            ->assertJsonPath('data.qualification_result', null)
            ->assertJsonPath('data.budget', null);
    }

    public function test_an_evaluation_is_stamped_with_its_evaluator_and_the_moment_it_was_made(): void
    {
        $this->authenticate();

        // Before the first save the form loads empty rather than answering that nothing is there.
        $this->getJson($this->endpoint('extended-details'))->assertOk()
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.qualification_result', null)
            ->assertJsonPath('data.evaluated_at', null);

        $response = $this->putJson($this->endpoint('extended-details'), [
            'budget' => 250000000,
            'budget_known' => true,
            'authority_note' => 'تصمیم با مدیر خرید است',
            'need_confirmed' => true,
            'timeframe' => 'سه‌ماهه چهارم',
            'qualification_result' => 'QUALIFIED',
        ])->assertOk()
            ->assertJsonPath('data.budget', 250000000)
            ->assertJsonPath('data.budget_known', true)
            ->assertJsonPath('data.need_confirmed', true)
            ->assertJsonPath('data.qualification_result', 'QUALIFIED')
            ->assertJsonPath('data.evaluated_by', ['user_id' => '1', 'display_name' => 'Test Operator']);

        self::assertNotNull($response->json('data.evaluated_at'));
        $this->assertDatabaseHas('crm_customer_extended_details', ['customer_id' => 11, 'evaluated_by' => 1]);
    }

    public function test_the_evaluation_keeps_only_its_latest_value_and_the_evaluator_moves_with_it(): void
    {
        $this->authenticate();
        DB::table('crm_customer_extended_details')->insert([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'created_by' => 2,
            'qualification_result' => 'PENDING', 'evaluated_by' => 2, 'budget' => 1000,
        ]);

        $this->putJson($this->endpoint('extended-details'), ['qualification_result' => 'QUALIFIED'])->assertOk()
            ->assertJsonPath('data.qualification_result', 'QUALIFIED')
            // What the form left out is cleared; there is no evaluation history to fall back on.
            ->assertJsonPath('data.budget', null)
            ->assertJsonPath('data.evaluated_by.user_id', '1');
        $this->assertDatabaseCount('crm_customer_extended_details', 1);
    }

    public function test_the_financial_summary_demands_the_day_it_describes_and_where_it_came_from(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint('financial-details'), ['credit_limit' => 500000000])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['field_errors' => ['financial_reference_date', 'source_note']]);
        $this->assertDatabaseCount('crm_customer_financial_details', 0);
    }

    public function test_the_financial_summary_is_written_and_read_back_whole(): void
    {
        $this->authenticate();

        $this->putJson($this->endpoint('financial-details'), [
            'credit_limit' => 500000000,
            'credit_rating' => 'LOW_RISK',
            'settlement_terms' => '۳۰ روزه',
            'financial_reference_date' => self::REGISTERED_ON,
            'source_note' => 'اعلام واحد مالی',
            'accounting_code' => '1101-22',
            'accounting_title' => 'بدهکاران تجاری',
            'balance' => -120000000,
        ])->assertOk()
            ->assertJsonPath('data.credit_limit', 500000000)
            ->assertJsonPath('data.credit_rating', 'LOW_RISK')
            ->assertJsonPath('data.financial_reference_date', self::REGISTERED_ON)
            ->assertJsonPath('data.source_note', 'اعلام واحد مالی')
            // A balance may stand on either side of the account, so it is signed.
            ->assertJsonPath('data.balance', -120000000)
            ->assertJsonPath('data.revenue', null);

        $this->getJson($this->endpoint('financial-details'))->assertOk()
            ->assertJsonPath('data.accounting_code', '1101-22')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.hq_id');
        $this->assertDatabaseCount('crm_customer_financial_details', 1);
    }

    public function test_an_unassessed_customer_answers_with_an_empty_financial_form(): void
    {
        $this->authenticate();

        $this->getJson($this->endpoint('financial-details'))->assertOk()
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.credit_limit', null)
            ->assertJsonPath('data.financial_reference_date', null);
    }

    public function test_the_money_of_a_customer_sits_behind_its_own_pair_of_permissions(): void
    {
        // The customer file is fully readable and writable, and the money still is not.
        $this->authenticate(permissions: ['customer.view', 'customer.edit']);

        $this->getJson($this->endpoint('financial-details'))->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->putJson($this->endpoint('financial-details'), $this->summary())->assertForbidden();
        $this->getJson($this->endpoint('extended-details'))->assertOk();

        $this->authenticate(permissions: ['crm.finance.view']);
        $this->getJson($this->endpoint('financial-details'))->assertOk();
        $this->putJson($this->endpoint('financial-details'), $this->summary())->assertForbidden()
            ->assertJsonPath('error_code', 'PERMISSION_DENIED');
    }

    #[DataProvider('deniedContexts')]
    public function test_access_is_denied_without_the_tenant_module_and_scope(
        ?string $hqId, bool $enabled, ScopeType $scope, string $errorCode): void
    {
        $this->authenticate($hqId, $enabled, scope: $scope);

        $this->getJson($this->endpoint('financial-details'))->assertForbidden()->assertJsonPath('error_code', $errorCode);
        $this->putJson($this->endpoint('financial-details'), $this->summary())->assertForbidden()->assertJsonPath('error_code', $errorCode);
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

    public function test_a_customer_outside_the_reach_of_the_actor_is_reported_as_missing(): void
    {
        $this->authenticate();
        $this->customer(['id' => 13, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'مشتری سازمان دیگر']);

        foreach (['extended-details', 'financial-details'] as $resource) {
            $this->getJson($this->endpoint($resource, '13'))->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
            $this->getJson($this->endpoint($resource, '99'))->assertNotFound();
        }
        $this->putJson($this->endpoint('financial-details', '13'), $this->summary())->assertNotFound();
        $this->putJson($this->endpoint('extended-details', '99'), ['qualification_result' => 'QUALIFIED'])->assertNotFound();
        $this->assertDatabaseCount('crm_customer_financial_details', 0);
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
            ['id' => 1, 'hq_code' => 'CUSTOMER-FINANCE', 'hq_title' => 'Customer finance tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer();
        // English field errors, so the assertions above read as lang/en/api.php writes them.
        $this->withHeader('Accept-Language', 'en');
    }

    private function endpoint(string $resource, string $customerId = '11'): string
    {
        return '/api/v1/crm/customers/'.$customerId.'/'.$resource;
    }

    private function summary(array $overrides = []): array
    {
        return array_replace([
            'financial_reference_date' => self::REGISTERED_ON,
            'source_note' => 'اعلام واحد مالی',
        ], $overrides);
    }

    private function customer(array $attributes = []): void
    {
        DB::table('crm_customers')->insert(array_replace([
            'id' => 11, 'hq_id' => 1, 'created_by' => 1, 'assignee_id' => null, 'kind' => 'COMPANY',
            'phase' => 'CUSTOMER', 'lifecycle' => 'ACTIVE', 'display_name' => 'پارس‌گستر آریا',
            'created_at' => '2026-09-28 10:00:00', 'updated_at' => '2026-09-28 10:00:00',
        ], $attributes));
    }

    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit', 'crm.finance.view', 'crm.finance.manage'],
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'customer-finance-session', $hqId, false, 'customer-finance-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('customer-finance')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('customer-finance');

        return $principal;
    }
}
