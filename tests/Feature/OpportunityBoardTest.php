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
 * The sales opportunity page: the funnels its columns come from, the board itself, the opening of an
 * opportunity, the moves across it and the history those moves leave behind.
 */
final class OpportunityBoardTest extends TestCase
{
    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_sales_funnel', 'crm_sales_funnel_steps',
        'crm_opportunities', 'crm_tasks', 'crm_activities', 'crm_opportunity_events',
    ];

    private const NULLABLE_MIGRATION = 'Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php';

    // 2026-11-01 at midnight UTC, the spelling every date in these payloads travels in.
    private const EXPECTED_CLOSE = 1793491200;

    public function test_the_funnel_list_carries_the_active_steps_the_board_draws_its_columns_from(): void
    {
        $this->authenticate();
        $this->step(['id' => 14, 'code' => 'RETIRED', 'title' => 'حذف‌شده', 'sort_order' => 4, 'is_active' => false]);
        $this->funnel(['id' => 2, 'code' => 'RETIRED_FUNNEL', 'title' => 'قیف بازنشسته', 'is_active' => false]);

        // Without the filter both the active and the retired funnels come back; the order is by title,
        // which is Persian text, so the set is what this asserts rather than the sequence.
        $all = $this->getJson('/api/v1/crm/sales-funnels')->assertOk();
        $ids = array_column($all->json('data'), 'sales_funnel_id');
        sort($ids);
        self::assertSame(['1', '2'], $ids);

        $active = $this->getJson('/api/v1/crm/sales-funnels?active=1')->assertOk()
            ->assertJsonPath('data.0.sales_funnel_id', '1')
            ->assertJsonPath('data.0.code', 'GENERAL')
            ->assertJsonPath('data.0.is_active', true);
        self::assertCount(1, $active->json('data'));
        // A retired step stays on its funnel but is never a board column.
        self::assertSame(['11', '13', '15'], array_column($active->json('data.0.steps'), 'sales_funnel_step_id'));
        self::assertSame(['OPEN', 'OPEN', 'WON'], array_column($active->json('data.0.steps'), 'outcome_type'));
    }

    public function test_the_board_answers_as_columns_so_a_step_nobody_reached_is_still_one(): void
    {
        $this->authenticate();
        $this->opportunity(['id' => 3, 'current_step_id' => 11, 'expected_close' => '2026-11-01']);
        $this->opportunity(['id' => 4, 'current_step_id' => 11, 'expected_close' => null, 'title' => 'بدون موعد']);
        $this->opportunity(['id' => 5, 'current_step_id' => 13, 'expected_close' => '2026-10-01']);

        $response = $this->getJson('/api/v1/crm/opportunities?funnel_id=1')->assertOk()
            ->assertJsonPath('data.funnel.sales_funnel_id', '1');

        $columns = $response->json('data.columns');
        self::assertSame(['11', '13', '15'], array_column(array_column($columns, 'step'), 'sales_funnel_step_id'));
        // An opportunity without an expected close carries no deadline, so it sorts behind every dated one.
        self::assertSame(['3', '4'], array_column($columns[0]['items'], 'opportunity_id'));
        self::assertSame(['5'], array_column($columns[1]['items'], 'opportunity_id'));
        // The won column is empty and still present, which a client grouping rows itself could not know.
        self::assertSame([], $columns[2]['items']);
    }

    public function test_a_card_names_its_customer_its_owner_and_the_task_that_comes_next(): void
    {
        $this->authenticate();
        $this->opportunity(['id' => 3, 'amount' => 180000000, 'probability' => 40]);
        $this->task(['id' => 1, 'opportunity_id' => 3, 'title' => 'پیگیری پیشنهاد', 'due_at' => '2026-10-05 08:00:00']);
        // A finished task is not what comes next, and a later one is not either.
        $this->task(['id' => 2, 'opportunity_id' => 3, 'title' => 'تماس بسته‌شده', 'status' => 'COMPLETED', 'due_at' => '2026-10-01 08:00:00']);
        $this->task(['id' => 3, 'opportunity_id' => 3, 'title' => 'جلسه بعدی', 'due_at' => '2026-11-20 08:00:00']);

        $this->getJson('/api/v1/crm/opportunities?funnel_id=1')->assertOk()
            ->assertJsonPath('data.columns.0.items.0.title', 'قرارداد ارسال دوره‌ای')
            ->assertJsonPath('data.columns.0.items.0.amount', 180000000)
            // A decimal string, so the stored two-place precision is never rounded by a float.
            ->assertJsonPath('data.columns.0.items.0.probability', '40.00')
            ->assertJsonPath('data.columns.0.items.0.expected_close', self::EXPECTED_CLOSE)
            ->assertJsonPath('data.columns.0.items.0.customer', ['customer_id' => '11', 'display_name' => 'پارس‌گستر آریا', 'phase' => 'CUSTOMER'])
            ->assertJsonPath('data.columns.0.items.0.assignee', ['user_id' => '1', 'display_name' => 'Test Operator'])
            ->assertJsonPath('data.columns.0.items.0.next_task.task_id', '1')
            ->assertJsonPath('data.columns.0.items.0.next_task.title', 'پیگیری پیشنهاد');
    }

    public function test_the_board_narrows_to_one_customer_or_one_owner(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'display_name' => 'دیگری', 'phase' => 'LEAD']);
        $this->opportunity(['id' => 3, 'customer_id' => 11, 'assignee_id' => 1]);
        $this->opportunity(['id' => 4, 'customer_id' => 12, 'assignee_id' => 1]);
        $this->opportunity(['id' => 5, 'customer_id' => 11, 'assignee_id' => 2]);

        $byCustomer = $this->getJson('/api/v1/crm/opportunities?funnel_id=1&customer_id=12')->assertOk();
        self::assertSame(['4'], array_column($byCustomer->json('data.columns.0.items'), 'opportunity_id'));
        // A lead carries opportunities exactly as a promoted customer does.
        self::assertSame('LEAD', $byCustomer->json('data.columns.0.items.0.customer.phase'));

        $byAssignee = $this->getJson('/api/v1/crm/opportunities?funnel_id=1&assignee_id=2')->assertOk();
        self::assertSame(['5'], array_column($byAssignee->json('data.columns.0.items'), 'opportunity_id'));
    }

    public function test_an_opportunity_opens_on_the_first_step_and_that_opening_is_written_to_the_history(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/v1/crm/opportunities', $this->draft())->assertCreated()
            ->assertJsonPath('data.opportunity.customer_id', '11')
            ->assertJsonPath('data.opportunity.funnel_id', '1')
            ->assertJsonPath('data.opportunity.title', 'قرارداد ارسال دوره‌ای')
            // The server puts it on the lowest active sort order; no client can file it halfway down.
            ->assertJsonPath('data.opportunity.current_step_id', '11')
            ->assertJsonPath('data.opportunity.current_step.code', 'DISCOVERY')
            ->assertJsonPath('data.opportunity.current_step.sort_order', 1)
            ->assertJsonPath('data.opportunity.close_reason', null)
            // The whole from_* group is null: there is no step the opportunity came from.
            ->assertJsonPath('data.event.from_step_id', null)
            ->assertJsonPath('data.event.from_outcome_type', null)
            ->assertJsonPath('data.event.to_step_code', 'DISCOVERY')
            ->assertJsonPath('data.event.to_outcome_type', 'OPEN');

        $this->assertDatabaseHas('crm_opportunities', [
            'id' => $response->json('data.opportunity.opportunity_id'),
            'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 11, 'created_by' => 1,
        ]);
        $this->assertDatabaseCount('crm_opportunity_events', 1);
    }

    public function test_the_starting_step_belongs_to_the_server_and_the_form_cannot_name_one(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/crm/opportunities', $this->draft(['current_step_id' => 15]))
            ->assertStatus(422)
            ->assertJsonStructure(['field_errors' => ['current_step_id']]);
        $this->assertDatabaseCount('crm_opportunities', 0);
    }

    #[DataProvider('unusableDrafts')]
    public function test_an_opportunity_is_refused_when_what_it_points_at_is_not_usable(array $changes, string $field): void
    {
        $this->authenticate();
        $this->customer(['id' => 13, 'hq_id' => 2, 'created_by' => 2, 'display_name' => 'مشتری سازمان دیگر']);
        $this->funnel(['id' => 3, 'code' => 'CLOSED', 'title' => 'قیف بسته', 'is_active' => false]);
        DB::table('users')->insert(['id' => 3, 'hq_id' => 1, 'first_name' => 'Left', 'last_name' => 'Operator', 'display_name' => 'Left Operator', 'status' => 'SUSPENDED']);

        $this->postJson('/api/v1/crm/opportunities', $this->draft($changes))
            ->assertStatus(422)
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_opportunities', 0);
        $this->assertDatabaseCount('crm_opportunity_events', 0);
    }

    public static function unusableDrafts(): array
    {
        return [
            'customer of another tenant' => [['customer_id' => 13], 'customer_id'],
            'customer that does not exist' => [['customer_id' => 99], 'customer_id'],
            'retired funnel' => [['funnel_id' => 3], 'funnel_id'],
            'funnel that does not exist' => [['funnel_id' => 99], 'funnel_id'],
            'suspended owner' => [['assignee_id' => 3], 'assignee_id'],
            'owner of another tenant' => [['assignee_id' => 2], 'assignee_id'],
        ];
    }

    public function test_a_funnel_without_an_active_step_can_hold_nothing(): void
    {
        $this->authenticate();
        $this->funnel(['id' => 4, 'code' => 'EMPTY', 'title' => 'قیف بی‌مرحله']);

        $this->postJson('/api/v1/crm/opportunities', $this->draft(['funnel_id' => 4]))
            ->assertStatus(422)
            ->assertJsonPath('field_errors.funnel_id', ['This funnel has no active step to open an opportunity on.']);
    }

    public function test_a_move_records_both_ends_as_they_read_at_the_moment_it_happened(): void
    {
        $this->authenticate();
        $this->opportunity(['id' => 3, 'current_step_id' => 11]);

        $this->postJson($this->transitionEndpoint('3'), ['from_step_id' => 11, 'to_step_id' => 13, 'reason' => 'جلسهٔ فنی برگزار شد'])
            ->assertCreated()
            ->assertJsonPath('data.opportunity.current_step_id', '13')
            ->assertJsonPath('data.opportunity.current_step.code', 'NEGOTIATION')
            ->assertJsonPath('data.event.from_step_code', 'DISCOVERY')
            ->assertJsonPath('data.event.from_outcome_type', 'OPEN')
            ->assertJsonPath('data.event.to_step_code', 'NEGOTIATION')
            ->assertJsonPath('data.event.reason', 'جلسهٔ فنی برگزار شد')
            ->assertJsonPath('data.event.actor', ['user_id' => '1', 'display_name' => 'Test Operator']);

        // Renaming the step afterwards never rewrites what the history says the operator did.
        DB::table('crm_sales_funnel_steps')->where('id', 11)->update(['title' => 'نام تازه']);
        $this->getJson($this->eventsEndpoint('3'))->assertOk()
            ->assertJsonPath('data.0.from_step_title', 'کشف نیاز');
    }

    public function test_a_second_operator_who_moved_it_first_makes_the_later_move_a_conflict(): void
    {
        $this->authenticate();
        $this->opportunity(['id' => 3, 'current_step_id' => 13]);

        $this->postJson($this->transitionEndpoint('3'), ['from_step_id' => 11, 'to_step_id' => 15])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'VERSION_CONFLICT')
            ->assertJsonPath('details.current_step_id', '13');
        $this->assertDatabaseCount('crm_opportunity_events', 0);
    }

    public function test_winning_needs_a_promoted_customer_and_the_interaction_that_proves_it(): void
    {
        $this->authenticate();
        $this->customer(['id' => 12, 'display_name' => 'سرنخ', 'phase' => 'LEAD']);
        $this->opportunity(['id' => 3, 'customer_id' => 12, 'current_step_id' => 13]);
        $this->opportunity(['id' => 4, 'customer_id' => 11, 'current_step_id' => 13]);
        $this->activity(['id' => 3301, 'customer_id' => 11]);
        $this->activity(['id' => 3302, 'customer_id' => 12]);

        // A deal is not won against a record that is still a lead.
        $this->postJson($this->transitionEndpoint('3'), ['from_step_id' => 13, 'to_step_id' => 15, 'evidence_activity_id' => 3302])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.to_step_id', ['Win the opportunity only against a record promoted to a customer.']);

        $this->postJson($this->transitionEndpoint('4'), ['from_step_id' => 13, 'to_step_id' => 15])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.evidence_activity_id', ['Name the interaction in which the customer accepted.']);

        // The evidence has to be an interaction with the customer being won.
        $this->postJson($this->transitionEndpoint('4'), ['from_step_id' => 13, 'to_step_id' => 15, 'evidence_activity_id' => 3302])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.evidence_activity_id', ['Select an interaction recorded with this same customer.']);

        $this->postJson($this->transitionEndpoint('4'), [
            'from_step_id' => 13, 'to_step_id' => 15, 'evidence_activity_id' => 3301, 'close_reason' => 'پذیرش پیشنهاد در جلسه',
        ])->assertCreated()
            ->assertJsonPath('data.opportunity.current_step_id', '15')
            ->assertJsonPath('data.opportunity.close_reason', 'پذیرش پیشنهاد در جلسه')
            ->assertJsonPath('data.event.to_outcome_type', 'WON')
            ->assertJsonPath('data.event.evidence_activity_id', '3301');
    }

    public function test_a_reason_for_closing_belongs_only_to_a_move_that_closes_and_is_dropped_on_reopening(): void
    {
        $this->authenticate();
        $this->opportunity(['id' => 3, 'current_step_id' => 11]);
        $this->activity(['id' => 3301, 'customer_id' => 11]);

        $this->postJson($this->transitionEndpoint('3'), ['from_step_id' => 11, 'to_step_id' => 13, 'close_reason' => 'چرا؟'])
            ->assertStatus(422)
            ->assertJsonPath('field_errors.close_reason', ['A reason for closing belongs only to a step that wins or loses the opportunity.']);

        $this->postJson($this->transitionEndpoint('3'), [
            'from_step_id' => 11, 'to_step_id' => 15, 'evidence_activity_id' => 3301, 'close_reason' => 'پذیرش پیشنهاد',
        ])->assertCreated();

        // Reopening drops the reason, so a stale one never outlives the outcome that explained it.
        $this->postJson($this->transitionEndpoint('3'), ['from_step_id' => 15, 'to_step_id' => 11])
            ->assertCreated()
            ->assertJsonPath('data.opportunity.close_reason', null);
    }

    #[DataProvider('refusedTransitions')]
    public function test_a_move_that_goes_nowhere_usable_is_refused(array $payload, string $field): void
    {
        $this->authenticate();
        $this->opportunity(['id' => 3, 'current_step_id' => 11]);
        $this->step(['id' => 14, 'code' => 'RETIRED', 'title' => 'حذف‌شده', 'sort_order' => 4, 'is_active' => false]);
        $this->funnel(['id' => 2, 'code' => 'OTHER', 'title' => 'قیف دیگر']);
        $this->step(['id' => 21, 'funnel_id' => 2, 'code' => 'START', 'title' => 'شروع', 'sort_order' => 1]);

        $this->postJson($this->transitionEndpoint('3'), $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseHas('crm_opportunities', ['id' => 3, 'current_step_id' => 11]);
        $this->assertDatabaseCount('crm_opportunity_events', 0);
    }

    public static function refusedTransitions(): array
    {
        return [
            'the step it already stands on' => [['from_step_id' => 11, 'to_step_id' => 11], 'to_step_id'],
            'a retired step' => [['from_step_id' => 11, 'to_step_id' => 14], 'to_step_id'],
            'a step of another funnel' => [['from_step_id' => 11, 'to_step_id' => 21], 'to_step_id'],
            'a step that does not exist' => [['from_step_id' => 11, 'to_step_id' => 99], 'to_step_id'],
            'a move in the future' => [['from_step_id' => 11, 'to_step_id' => 13, 'occurred_at' => 4102444800], 'occurred_at'],
        ];
    }

    public function test_the_history_reads_oldest_first_as_the_story_of_the_opportunity(): void
    {
        $this->authenticate();
        $this->postJson('/api/v1/crm/opportunities', $this->draft())->assertCreated();
        $this->postJson($this->transitionEndpoint('1'), ['from_step_id' => 11, 'to_step_id' => 13])->assertCreated();

        $response = $this->getJson($this->eventsEndpoint('1'))->assertOk();
        self::assertSame([null, 'DISCOVERY'], array_column($response->json('data'), 'from_step_code'));
        self::assertSame(['DISCOVERY', 'NEGOTIATION'], array_column($response->json('data'), 'to_step_code'));
    }

    public function test_reading_needs_the_view_permission_and_moving_needs_the_manage_one(): void
    {
        $this->authenticate(permissions: ['crm.opportunity.view']);
        $this->opportunity(['id' => 3, 'current_step_id' => 11]);

        $this->getJson('/api/v1/crm/sales-funnels')->assertOk();
        $this->getJson('/api/v1/crm/opportunities?funnel_id=1')->assertOk();
        $this->getJson($this->eventsEndpoint('3'))->assertOk();
        $this->postJson('/api/v1/crm/opportunities', $this->draft())->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
        $this->postJson($this->transitionEndpoint('3'), ['from_step_id' => 11, 'to_step_id' => 13])->assertForbidden();

        // The customer permissions say nothing about the pipeline.
        $this->authenticate(permissions: ['customer.view', 'customer.edit']);
        $this->getJson('/api/v1/crm/sales-funnels')->assertForbidden()->assertJsonPath('error_code', 'PERMISSION_DENIED');
    }

    #[DataProvider('deniedContexts')]
    public function test_access_is_denied_without_the_tenant_module_and_scope(
        ?string $hqId, bool $enabled, ScopeType $scope, string $errorCode): void
    {
        $this->authenticate($hqId, $enabled, scope: $scope);

        $this->getJson('/api/v1/crm/sales-funnels')->assertForbidden()->assertJsonPath('error_code', $errorCode);
        $this->postJson('/api/v1/crm/opportunities', $this->draft())->assertForbidden()->assertJsonPath('error_code', $errorCode);
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

    public function test_a_funnel_or_an_opportunity_outside_the_reach_of_the_actor_is_reported_as_missing(): void
    {
        $this->authenticate();
        $this->funnel(['id' => 5, 'hq_id' => 2, 'created_by' => 2, 'code' => 'FOREIGN', 'title' => 'قیف سازمان دیگر']);

        $this->getJson('/api/v1/crm/opportunities?funnel_id=5')->assertNotFound()->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
        $this->getJson('/api/v1/crm/opportunities?funnel_id=99')->assertNotFound();
        $this->getJson($this->eventsEndpoint('99'))->assertNotFound();
        $this->postJson($this->transitionEndpoint('99'), ['from_step_id' => 11, 'to_step_id' => 13])->assertNotFound();
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
            ['id' => 1, 'hq_code' => 'CRM-PIPELINE', 'hq_title' => 'CRM pipeline tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Operator', 'display_name' => 'Test Operator', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Operator', 'display_name' => 'Other Operator', 'status' => 'ACTIVE'],
        ]);
        $this->customer();
        $this->funnel();
        $this->step(['id' => 11, 'code' => 'DISCOVERY', 'title' => 'کشف نیاز', 'sort_order' => 1]);
        $this->step(['id' => 13, 'code' => 'NEGOTIATION', 'title' => 'مذاکره', 'sort_order' => 2]);
        $this->step(['id' => 15, 'code' => 'WON', 'title' => 'موفق', 'sort_order' => 3, 'outcome_type' => 'WON']);
        // English field errors, so the assertions above read as lang/en/api.php writes them.
        $this->withHeader('Accept-Language', 'en');
    }

    private function transitionEndpoint(string $opportunityId): string
    {
        return '/api/v1/crm/opportunities/'.$opportunityId.'/step-transitions';
    }

    private function eventsEndpoint(string $opportunityId): string
    {
        return '/api/v1/crm/opportunities/'.$opportunityId.'/events';
    }

    private function draft(array $overrides = []): array
    {
        return array_replace([
            'customer_id' => 11,
            'funnel_id' => 1,
            'title' => 'قرارداد ارسال دوره‌ای',
            'assignee_id' => 1,
            'amount' => 180000000,
            'probability' => 40,
            'expected_close' => self::EXPECTED_CLOSE,
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

    private function funnel(array $attributes = []): void
    {
        DB::table('crm_sales_funnel')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'code' => 'GENERAL', 'title' => 'قیف فروش عمومی',
            'is_active' => true, 'created_by' => 1,
        ], $attributes));
    }

    private function step(array $attributes = []): void
    {
        DB::table('crm_sales_funnel_steps')->insert(array_replace([
            'id' => 11, 'hq_id' => 1, 'funnel_id' => 1, 'code' => 'DISCOVERY', 'title' => 'کشف نیاز',
            'sort_order' => 1, 'outcome_type' => 'OPEN', 'is_active' => true, 'created_by' => 1,
        ], $attributes));
    }

    private function opportunity(array $attributes = []): void
    {
        DB::table('crm_opportunities')->insert(array_replace([
            'id' => 3, 'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 11,
            'assignee_id' => 1, 'title' => 'قرارداد ارسال دوره‌ای', 'amount' => 180000000,
            'probability' => 40, 'expected_close' => '2026-11-01', 'created_by' => 1,
        ], $attributes));
    }

    private function task(array $attributes = []): void
    {
        DB::table('crm_tasks')->insert(array_replace([
            'id' => 1, 'hq_id' => 1, 'title' => 'پیگیری پیشنهاد', 'status' => 'OPEN', 'priority' => 'MEDIUM',
            'assignee_id' => 1, 'customer_id' => 11, 'opportunity_id' => 3, 'created_by' => 1,
        ], $attributes));
    }

    private function activity(array $attributes = []): void
    {
        DB::table('crm_activities')->insert(array_replace([
            'id' => 3301, 'hq_id' => 1, 'type' => 'MEETING', 'occurred_at' => '2026-09-29 10:00:00',
            'customer_id' => 11, 'created_by' => 1,
        ], $attributes));
    }

    private function authenticate(
        ?string $hqId = '1',
        bool $enabled = true,
        array $permissions = ['crm.opportunity.view', 'crm.opportunity.manage'],
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'crm-pipeline-session', $hqId, false, 'crm-pipeline-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('crm-pipeline')->andReturn($claims));
        $this->mock(AccessSessionValidatorInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('validate')->with($claims)->andReturn($principal));
        $context = new AccessContextDto(
            hqId: $hqId,
            permissions: $permissions,
            permissionScopes: array_fill_keys($permissions, [new PermissionScope($scope, $scope === ScopeType::TENANT ? null : '1')]),
            moduleEntitlements: [new ModuleEntitlementDto('Customer', $enabled ? EntitlementStatus::ENABLED : EntitlementStatus::DISABLED)],
        );
        $this->mock(AccessContextResolverInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('resolve')->with($principal)->andReturn($context));
        $this->withToken('crm-pipeline');

        return $principal;
    }
}
