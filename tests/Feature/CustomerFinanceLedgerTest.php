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

/**
 * The money side of the supplementary-information tab: bank accounts, references to invoices issued
 * outside the CRM, the manual ledger of a customer and what a receipt in it settles.
 */
final class CustomerFinanceLedgerTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_bank_accounts',
        'crm_external_invoices', 'crm_financial_entries', 'crm_financial_allocations',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    // 2026-09-05 and 2026-10-05 at midnight UTC, the spelling every date in these payloads travels in.
    private const ISSUED_ON = 1788566400;

    private const DUE_ON = 1791158400;

    public function test_a_bank_account_leaves_the_system_masked_and_never_whole(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint('bank-accounts'), [
            'bank_name' => 'ملت',
            'status' => 'ACTIVE',
            'iban' => 'IR820120000000001234567890',
            'card_number' => '6104337899521102',
            'account_no' => '1234567890',
        ])->assertCreated()
            ->assertJsonPath('data.bank_name', 'ملت')
            ->assertJsonPath('data.iban_masked', 'IR********************7890')
            ->assertJsonPath('data.card_number_masked', '610433******1102')
            ->assertJsonPath('data.account_no_masked', '******7890')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonMissingPath('data.iban')
            ->assertJsonMissingPath('data.card_number')
            ->assertJsonMissingPath('data.account_no');

        // The whole numbers are still stored, because a transfer has to be made from them.
        $this->assertDatabaseHas('crm_bank_accounts', [
            'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1,
            'iban' => 'IR820120000000001234567890', 'card_number' => '6104337899521102',
        ]);
    }

    public function test_pasted_numbers_keep_their_digits_and_lose_their_spacing(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint('bank-accounts'), [
            'bank_name' => 'تجارت',
            'status' => 'ACTIVE',
            'iban' => 'ir14 0180 0000 0000 9876 5432 10',
            'card_number' => '5859-8310-4411-3322',
        ])->assertCreated()->assertJsonPath('data.iban_masked', 'IR********************3210');

        $this->assertDatabaseHas('crm_bank_accounts', ['iban' => 'IR140180000000009876543210', 'card_number' => '5859831044113322']);
    }

    public function test_the_first_account_is_the_primary_one_and_the_flag_moves_rather_than_multiplies(): void
    {
        $this->authenticate();
        $first = $this->postJson($this->endpoint('bank-accounts'), $this->account(['is_primary' => false]))
            ->assertCreated()->assertJsonPath('data.is_primary', true);

        $this->postJson($this->endpoint('bank-accounts'), $this->account(['bank_name' => 'تجارت']))
            ->assertCreated()->assertJsonPath('data.is_primary', false);

        $claimed = $this->postJson($this->endpoint('bank-accounts'), $this->account(['bank_name' => 'سامان', 'is_primary' => true]))
            ->assertCreated()->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('crm_bank_accounts', ['id' => $first->json('data.bank_account_id'), 'is_primary' => false]);
        $this->assertDatabaseHas('crm_bank_accounts', ['id' => $claimed->json('data.bank_account_id'), 'is_primary' => true]);

        $listed = $this->getJson($this->endpoint('bank-accounts'))->assertOk();
        // The primary account is listed first, whenever it was added.
        self::assertSame([true, false, false], array_column($listed->json('data'), 'is_primary'));
        self::assertSame('سامان', $listed->json('data.0.bank_name'));
    }

    public function test_an_account_nobody_can_pay_into_and_an_inactive_primary_are_both_refused(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint('bank-accounts'), ['bank_name' => 'ملت', 'status' => 'ACTIVE'])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.iban', ['Enter an IBAN, a card number or an account number for the account.']);

        $this->postJson($this->endpoint('bank-accounts'), $this->account(['status' => 'INACTIVE', 'is_primary' => true]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.is_primary', ['Only an active account can be the primary one.']);
        $this->assertDatabaseCount('crm_bank_accounts', 0);
    }

    public function test_one_document_of_one_external_system_is_recorded_once_for_the_whole_tenant(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint('external-invoices'), $this->invoice())->assertCreated()
            ->assertJsonPath('data.external_system', 'سپیدار')
            ->assertJsonPath('data.reference_no', 'INV-2231')
            ->assertJsonPath('data.amount', 120000000)
            ->assertJsonPath('data.issued_on', self::ISSUED_ON)
            ->assertJsonPath('data.due_on', self::DUE_ON)
            ->assertJsonPath('data.contract_id', null);

        $this->postJson($this->endpoint('external-invoices'), $this->invoice())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CONFLICT');
        $this->assertDatabaseCount('crm_external_invoices', 1);
    }

    public function test_an_invoice_cannot_fall_due_before_it_was_issued_or_cite_a_stranger_opportunity(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint('external-invoices'), $this->invoice(['due_on' => self::ISSUED_ON - 86400]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.due_on', ['The due date cannot fall before the issue date.']);

        $this->postJson($this->endpoint('external-invoices'), $this->invoice(['opportunity_id' => 404]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.opportunity_id', ['Select an opportunity of this same customer.']);
        $this->assertDatabaseCount('crm_external_invoices', 0);
    }

    public function test_the_ledger_is_returned_newest_first_and_can_be_narrowed_to_one_kind(): void
    {
        $this->authenticate();
        $this->entry(['id' => 1, 'kind' => 'REVENUE', 'effective_on' => '2026-09-01']);
        $this->entry(['id' => 2, 'kind' => 'RECEIPT', 'effective_on' => '2026-09-10']);
        // Another customer of the same tenant keeps its own ledger.
        $this->customer(['id' => 12, 'display_name' => 'دیگری']);
        $this->entry(['id' => 3, 'customer_id' => 12, 'kind' => 'RECEIPT']);

        $all = $this->getJson($this->endpoint('financial-entries'))->assertOk();
        self::assertSame(['2', '1'], array_column($all->json('data'), 'financial_entry_id'));
        // Nothing is allocated yet, which is zero rather than unknown.
        self::assertSame([0, 0], array_column($all->json('data'), 'allocated'));

        $receipts = $this->getJson($this->endpoint('financial-entries').'?kind=RECEIPT')->assertOk();
        self::assertSame(['2'], array_column($receipts->json('data'), 'financial_entry_id'));
    }

    public function test_an_entry_is_recorded_with_the_day_the_money_moved_and_where_the_figure_came_from(): void
    {
        $this->authenticate();

        $this->postJson($this->endpoint('financial-entries'), [
            'kind' => 'RECEIPT',
            'amount' => 100000000,
            'effective_on' => self::ISSUED_ON,
            'source_ref' => 'رسید بانکی 8812',
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'RECEIPT')
            ->assertJsonPath('data.amount', 100000000)
            ->assertJsonPath('data.effective_on', self::ISSUED_ON)
            ->assertJsonPath('data.source_ref', 'رسید بانکی 8812')
            ->assertJsonPath('data.allocated', 0)
            ->assertJsonPath('data.reverses_id', null);

        $this->assertDatabaseHas('crm_financial_entries', [
            'hq_id' => 1, 'customer_id' => 11, 'created_by' => 1, 'effective_on' => '2026-09-05',
        ]);
    }

    public function test_a_correction_is_a_reversal_of_the_same_kind_and_never_happens_twice(): void
    {
        $this->authenticate();
        $this->entry(['id' => 1, 'kind' => 'RECEIPT', 'amount' => 100000000]);

        $this->postJson($this->endpoint('financial-entries'), $this->draft(['kind' => 'REVENUE', 'reverses_id' => 1]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.reverses_id', ['A reversal must carry the same kind as the entry it reverses.']);

        $this->postJson($this->endpoint('financial-entries'), $this->draft(['amount' => -100000000, 'reverses_id' => 1]))
            ->assertCreated()
            ->assertJsonPath('data.amount', -100000000)
            ->assertJsonPath('data.reverses_id', '1');

        $this->postJson($this->endpoint('financial-entries'), $this->draft(['amount' => -100000000, 'reverses_id' => 1]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.reverses_id', ['This entry has already been reversed.']);
    }

    public function test_an_entry_can_only_cite_a_document_of_its_own_customer(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'display_name' => 'دیگری']);
        $this->invoiceRow(['id' => 1, 'customer_id' => 12]);

        $this->postJson($this->endpoint('financial-entries'), $this->draft(['invoice_id' => 1]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.invoice_id', ['Select an invoice of this same customer.']);

        $this->postJson($this->endpoint('financial-entries'), $this->draft(['reverses_id' => 404]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.reverses_id', ['Select an entry of this same customer.']);
        $this->assertDatabaseCount('crm_financial_entries', 0);
    }

    public function test_a_receipt_is_allocated_until_nothing_is_left_of_it(): void
    {
        $this->authenticate();
        $this->invoiceRow(['id' => 1]);
        $this->entry(['id' => 1, 'kind' => 'RECEIPT', 'amount' => 100000000]);

        $this->postJson($this->allocationEndpoint('1'), ['invoice_id' => 1, 'amount' => 60000000])
            ->assertCreated()
            ->assertJsonPath('data.receipt_entry_id', '1')
            ->assertJsonPath('data.invoice_id', '1')
            ->assertJsonPath('data.amount', 60000000)
            ->assertJsonPath('data.remaining', 40000000);

        // The ledger now reports how much of the receipt is spoken for.
        $this->getJson($this->endpoint('financial-entries'))->assertOk()->assertJsonPath('data.0.allocated', 60000000);

        $this->postJson($this->allocationEndpoint('1'), ['invoice_id' => 1, 'amount' => 40000001])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ALLOCATION_EXCEEDS_RECEIPT')
            ->assertJsonPath('details.remaining', 40000000);

        $this->postJson($this->allocationEndpoint('1'), ['invoice_id' => 1, 'amount' => 40000000])
            ->assertCreated()->assertJsonPath('data.remaining', 0);
        $this->assertDatabaseCount('crm_financial_allocations', 2);
    }

    public function test_only_a_receipt_is_allocated_and_only_against_a_document_of_its_customer(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'display_name' => 'دیگری']);
        $this->invoiceRow(['id' => 1, 'customer_id' => 12]);
        $this->entry(['id' => 1, 'kind' => 'REVENUE', 'amount' => 100000000]);
        $this->entry(['id' => 2, 'kind' => 'RECEIPT', 'amount' => 100000000]);

        $response = $this->postJson($this->allocationEndpoint('1'), ['invoice_id' => 1, 'amount' => 1000])->assertStatus(422);
        // assertJsonPath reads '*' as a wildcard, so the catch-all key is taken off the body itself.
        self::assertSame(['Only a receipt can be allocated against an invoice.'], $response->json('field_errors')['*']);

        $this->postJson($this->allocationEndpoint('2'), ['invoice_id' => 1, 'amount' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.invoice_id', ['Select an invoice of this same customer.']);

        // An entry of another tenant is indistinguishable from one that does not exist.
        $this->postJson($this->allocationEndpoint('404'), ['invoice_id' => 1, 'amount' => 1000])->assertNotFound();
        $this->assertDatabaseCount('crm_financial_allocations', 0);
    }

    public function test_reading_needs_the_finance_view_permission_and_writing_needs_the_manage_one(): void
    {
        $this->authenticate(permissions: ['customer.view', 'customer.edit']);
        foreach (['bank-accounts', 'external-invoices', 'financial-entries'] as $resource) {
            $this->getJson($this->endpoint($resource))->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        }

        $this->authenticate(permissions: ['crm.finance.view']);
        $this->getJson($this->endpoint('financial-entries'))->assertOk();
        $this->postJson($this->endpoint('bank-accounts'), $this->account())->assertForbidden();
        $this->postJson($this->endpoint('external-invoices'), $this->invoice())->assertForbidden();
        $this->postJson($this->endpoint('financial-entries'), $this->draft())->assertForbidden();
        $this->postJson($this->allocationEndpoint('1'), ['invoice_id' => 1, 'amount' => 1000])->assertForbidden();
    }

    public function test_a_customer_outside_the_reach_of_the_actor_is_reported_as_missing(): void
    {
        $this->authenticate();
        $this->customer(['id' => 13, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'مشتری سازمان دیگر']);

        foreach (['bank-accounts', 'external-invoices', 'financial-entries'] as $resource) {
            $this->getJson($this->endpoint($resource, '13'))->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
            $this->getJson($this->endpoint($resource, '99'))->assertNotFound();
        }
        $this->postJson($this->endpoint('bank-accounts', '13'), $this->account())->assertNotFound();
        $this->postJson($this->endpoint('financial-entries', '99'), $this->draft())->assertNotFound();
        $this->assertDatabaseCount('crm_bank_accounts', 0);
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
            ['id' => 1, 'hq_code' => 'CRM-FINANCE', 'hq_title' => 'CRM finance tenant'],
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

    private function allocationEndpoint(string $entryId): string
    {
        return '/api/v1/crm/financial-entries/'.$entryId.'/allocations';
    }

    private function account(array $overrides = []): array
    {
        return array_replace([
            'bank_name' => 'ملت',
            'status' => 'ACTIVE',
            'iban' => 'IR820120000000001234567890',
        ], $overrides);
    }

    private function invoice(array $overrides = []): array
    {
        return array_replace([
            'external_system' => 'سپیدار',
            'reference_no' => 'INV-2231',
            'amount' => 120000000,
            'issued_on' => self::ISSUED_ON,
            'due_on' => self::DUE_ON,
        ], $overrides);
    }

    private function draft(array $overrides = []): array
    {
        return array_replace([
            'kind' => 'RECEIPT',
            'amount' => 100000000,
            'effective_on' => self::ISSUED_ON,
            'source_ref' => 'رسید بانکی 8812',
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

    private function invoiceRow(array $attributes = []): void
    {
        DB::table('crm_external_invoices')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'external_system' => 'سپیدار',
            'reference_no' => 'INV-'.($attributes['id'] ?? 1), 'amount' => 120000000,
            'issued_on' => '2026-09-05', 'created_by' => 1,
        ], $attributes));
    }

    private function entry(array $attributes = []): void
    {
        DB::table('crm_financial_entries')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'customer_id' => 11, 'kind' => 'RECEIPT', 'amount' => 100000000,
            'effective_on' => '2026-09-10', 'source_ref' => 'رسید بانکی 8812', 'created_by' => 1,
        ], $attributes));
    }

    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['customer.view', 'customer.edit', 'crm.finance.view', 'crm.finance.manage'],
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'crm-finance-session', $hqId, false, 'crm-finance-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('crm-finance')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('crm-finance');

        return $principal;
    }
}
