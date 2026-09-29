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

final class RecordActivityTest extends TestCase
{
    private const ENDPOINT = '/api/v1/crm/activities';

    private const TABLES = [
        'hq_tenants', 'users', 'crm_customers', 'crm_catalog_categories', 'crm_catalog_personas',
        'crm_catalog_sales_models', 'crm_catalog_items', 'crm_sales_funnel', 'crm_sales_funnel_steps',
        'crm_opportunities', 'crm_tasks', 'crm_task_assignment_events', 'crm_activities',
        'crm_activity_participants', 'idempotency_records',
    ];

    public function test_a_call_is_recorded_against_a_customer_and_read_back_with_its_detail(): void
    {
        $this->authenticate();

        $this->record($this->call())->assertCreated()
            ->assertJsonPath('data.type', 'CALL')
            ->assertJsonPath('data.occurred_at', '2026-09-29T06:30:00.000000Z')
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.opportunity_id', null)
            ->assertJsonPath('data.task_id', null)
            ->assertJsonPath('data.contact_customer_id', '13')
            ->assertJsonPath('data.direction', 'OUTBOUND')
            ->assertJsonPath('data.call_outcome', 'ANSWERED')
            ->assertJsonPath('data.duration_minutes', 5)
            ->assertJsonPath('data.participants', [])
            ->assertJsonPath('data.created_by', '1');

        $this->assertDatabaseHas('crm_activities', ['hq_id' => 1, 'type' => 'CALL', 'customer_id' => 11, 'created_by' => 1]);
    }

    public function test_a_meeting_keeps_its_participants_and_takes_its_customer_from_the_opportunity(): void
    {
        $this->authenticate();

        $response = $this->record($this->meeting())->assertCreated()
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.opportunity_id', '3')
            ->assertJsonPath('data.meeting_mode', 'ONLINE')
            ->assertJsonPath('data.participants', [['user_id' => '1', 'minutes' => 45], ['user_id' => '3', 'minutes' => 30]]);

        $this->assertDatabaseCount('crm_activity_participants', 2);
        $this->assertDatabaseHas('crm_activity_participants', [
            'hq_id' => 1, 'activity_id' => $response->json('data.activity_id'), 'user_id' => 3, 'minutes' => 30, 'created_by' => 1,
        ]);
    }

    public function test_an_interaction_on_a_task_inherits_where_the_task_is_filed(): void
    {
        $this->authenticate();

        $this->record(['type' => 'NOTE', 'occurred_at' => '2026-09-29T10:00:00Z', 'task_id' => 5, 'body' => 'یادداشت'])->assertCreated()
            ->assertJsonPath('data.task_id', '5')
            ->assertJsonPath('data.customer_id', '11')
            ->assertJsonPath('data.opportunity_id', '3');

        $this->record(['type' => 'NOTE', 'occurred_at' => '2026-09-29T10:00:00Z', 'task_id' => 6, 'body' => 'داخلی'])->assertCreated()
            ->assertJsonPath('data.customer_id', null);
    }

    public function test_messages_and_documents_are_recorded_too(): void
    {
        $this->authenticate();

        $this->record([
            'type' => 'MESSAGE', 'occurred_at' => '2026-09-29T10:00:00Z', 'customer_id' => 11,
            'direction' => 'INBOUND', 'channel' => 'SMS', 'contact_value' => '09121234567',
        ])->assertCreated()->assertJsonPath('data.channel', 'SMS');
        $this->record([
            'type' => 'DOCUMENT_SENT', 'occurred_at' => '2026-09-29T10:00:00Z', 'customer_id' => 11, 'document_version_id' => 8,
        ])->assertCreated()->assertJsonPath('data.document_version_id', '8');
    }

    public function test_a_retried_request_records_the_interaction_once(): void
    {
        $this->authenticate();

        $first = $this->withHeader('Idempotency-Key', 'replayed-activity-0001')->postJson(self::ENDPOINT, $this->call())->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'replayed-activity-0001')->postJson(self::ENDPOINT, $this->call())->assertCreated();

        $this->assertSame($first->json('data.activity_id'), $second->json('data.activity_id'));
        $this->assertDatabaseCount('crm_activities', 1);
    }

    public function test_a_request_without_an_idempotency_key_is_refused(): void
    {
        $this->authenticate();

        $this->postJson(self::ENDPOINT, $this->call())->assertUnprocessable()->assertJsonStructure(['field_errors' => ['Idempotency-Key']]);
        $this->assertDatabaseCount('crm_activities', 0);
    }

    /** @param array<string, mixed> $changes */
    #[DataProvider('refusedInteractions')]
    public function test_an_incoherent_interaction_names_the_field_that_is_wrong(array $changes, string $field): void
    {
        $this->authenticate();

        $this->record(array_replace($this->call(), $changes))->assertUnprocessable()->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_activities', 0);
        $this->assertDatabaseCount('crm_activity_participants', 0);
    }

    public static function refusedInteractions(): array
    {
        $note = ['type' => 'NOTE', 'body' => 'x', 'direction' => null, 'contact_value' => null, 'call_outcome' => null, 'duration_minutes' => null, 'contact_customer_id' => null];

        return [
            'a referral cannot be entered by hand' => [['type' => 'REFERRAL'], 'type'],
            'no customer, opportunity or task' => [['customer_id' => null], 'customer_id'],
            'a note without a body' => [array_replace($note, ['body' => null]), 'body'],
            'a call outcome on a note' => [['call_outcome' => 'BUSY'] + $note, 'call_outcome'],
            'a duration on a note' => [['duration_minutes' => 3] + $note, 'duration_minutes'],
            'a direction on a meeting' => [['type' => 'MEETING', 'meeting_mode' => 'ONLINE', 'meeting_url' => 'https://x.test', 'call_outcome' => null], 'direction'],
            'a place on a call' => [['location' => 'دفتر'], 'location'],
            'a document on a call' => [['document_version_id' => 3], 'document_version_id'],
            'participants on a call' => [['participants' => [['user_id' => 1, 'minutes' => 1]]], 'participants'],
            'a call without an outcome' => [['call_outcome' => null], 'call_outcome'],
            'a message without a channel' => [['type' => 'MESSAGE', 'call_outcome' => null, 'duration_minutes' => null], 'channel'],
            'a meeting without a mode' => [['type' => 'MEETING', 'call_outcome' => null, 'direction' => null, 'contact_value' => null, 'location' => 'دفتر'], 'meeting_mode'],
            'a meeting without a place or a link' => [['type' => 'MEETING', 'call_outcome' => null, 'direction' => null, 'contact_value' => null, 'meeting_mode' => 'IN_PERSON'], 'location'],
            'a company as the contact' => [['contact_customer_id' => 12], 'contact_customer_id'],
            'a contact of another tenant' => [['contact_customer_id' => 99], 'contact_customer_id'],
            'a customer of another tenant' => [['customer_id' => 99], 'customer_id'],
            'an opportunity of another tenant' => [['customer_id' => null, 'opportunity_id' => 9], 'opportunity_id'],
            'an opportunity of another customer' => [['opportunity_id' => 4], 'opportunity_id'],
            'a task of another tenant' => [['customer_id' => null, 'task_id' => 7], 'task_id'],
            'a task filed against another customer' => [['customer_id' => 12, 'task_id' => 5], 'customer_id'],
            'a task filed against another opportunity' => [['customer_id' => null, 'opportunity_id' => 4, 'task_id' => 5], 'opportunity_id'],
            'an unknown type' => [['type' => 'FAX'], 'type'],
            'no time' => [['occurred_at' => null], 'occurred_at'],
        ];
    }

    /** @param list<array{user_id: int, minutes: int}> $participants */
    #[DataProvider('refusedParticipants')]
    public function test_the_people_at_a_meeting_are_active_colleagues_named_once(array $participants, string $field): void
    {
        $this->authenticate();

        $this->record(['participants' => $participants] + $this->meeting())->assertUnprocessable()
            ->assertJsonStructure(['field_errors' => [$field]]);
        $this->assertDatabaseCount('crm_activities', 0);
    }

    public static function refusedParticipants(): array
    {
        return [
            'the same person twice' => [[['user_id' => 1, 'minutes' => 1], ['user_id' => 1, 'minutes' => 2]], 'participants.1.user_id'],
            'an invited user' => [[['user_id' => 4, 'minutes' => 1]], 'participants.0.user_id'],
            'a user of another tenant' => [[['user_id' => 2, 'minutes' => 1]], 'participants.0.user_id'],
            'negative minutes' => [[['user_id' => 1, 'minutes' => -1]], 'participants.0.minutes'],
        ];
    }

    public function test_the_task_action_endpoint_still_answers_with_the_lighter_activity(): void
    {
        $this->authenticate(['crm.task.manage']);

        $response = $this->postJson('/api/v1/tasks/5/actions', ['activity' => [
            'type' => 'CALL', 'occurred_at' => '2026-09-29T10:00:00Z', 'call_outcome' => 'ANSWERED', 'duration_minutes' => 2,
        ]])->assertCreated()->assertJsonPath('data.activity.call_outcome', 'ANSWERED');
        $this->assertArrayNotHasKey('participants', $response->json('data.activity'));

        $this->postJson('/api/v1/tasks/5/actions', ['activity' => [
            'type' => 'NOTE', 'occurred_at' => '2026-09-29T10:00:00Z', 'call_outcome' => 'ANSWERED',
        ]])->assertUnprocessable()->assertJsonStructure(['field_errors' => ['activity.call_outcome']]);
    }

    public function test_recording_needs_the_activity_permission_not_the_task_one(): void
    {
        $this->authenticate(['crm.task.manage']);

        $this->record($this->call())->assertForbidden();
        $this->assertDatabaseCount('crm_activities', 0);
    }

    #[DataProvider('deniedContexts')]
    public function test_tenant_entitlement_and_scope_checks_guard_the_endpoint(?string $hqId, bool $enabled, ScopeType $scope, string $error): void
    {
        $this->authenticate(hqId: $hqId, enabled: $enabled, scope: $scope);

        $this->record($this->call())->assertForbidden()->assertJsonPath('error_code', $error);
        $this->assertDatabaseCount('crm_activities', 0);
    }

    public static function deniedContexts(): array
    {
        return [
            'no tenant' => [null, true, ScopeType::TENANT, 'TENANT_ACCESS_DENIED'],
            'disabled module' => ['1', false, ScopeType::TENANT, 'ENTITLEMENT_DISABLED'],
            'node scope' => ['1', true, ScopeType::NODE, 'SCOPE_ACCESS_DENIED'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.foreign_key_constraints' => true]);
        DB::purge('sqlite');
        foreach (self::TABLES as $table) {
            (require glob(base_path('Modules/*/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require base_path('Modules/Customer/database/migrations/2026_09_28_000330_make_crm_customers_assignee_nullable.php'))->up();
        DB::table('hq_tenants')->insert([
            ['id' => 1, 'hq_code' => 'CRM-TASK', 'hq_title' => 'Task tenant'],
            ['id' => 2, 'hq_code' => 'OTHER', 'hq_title' => 'Another tenant'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'hq_id' => 1, 'first_name' => 'Test', 'last_name' => 'Seller', 'display_name' => 'Test Seller', 'status' => 'ACTIVE'],
            ['id' => 2, 'hq_id' => 2, 'first_name' => 'Other', 'last_name' => 'Seller', 'display_name' => 'Other Seller', 'status' => 'ACTIVE'],
            ['id' => 3, 'hq_id' => 1, 'first_name' => 'Second', 'last_name' => 'Seller', 'display_name' => 'Second Seller', 'status' => 'ACTIVE'],
            ['id' => 4, 'hq_id' => 1, 'first_name' => 'Invited', 'last_name' => 'Seller', 'display_name' => 'Invited Seller', 'status' => 'INVITED'],
        ]);
        DB::table('crm_customers')->insert([
            ['id' => 11, 'hq_id' => 1, 'created_by' => 1, 'kind' => 'COMPANY', 'phase' => 'CUSTOMER', 'display_name' => 'پارس‌گستر آریا', 'customer_code' => 'C-1001'],
            ['id' => 12, 'hq_id' => 1, 'created_by' => 1, 'kind' => 'COMPANY', 'phase' => 'LEAD', 'display_name' => 'سرنخ نمونه', 'customer_code' => null],
            ['id' => 13, 'hq_id' => 1, 'created_by' => 1, 'kind' => 'PERSON', 'phase' => 'LEAD', 'display_name' => 'سارا احمدی', 'customer_code' => null],
            ['id' => 99, 'hq_id' => 2, 'created_by' => 2, 'kind' => 'PERSON', 'phase' => 'LEAD', 'display_name' => 'مخاطب سازمان دیگر', 'customer_code' => null],
        ]);
        DB::table('crm_sales_funnel')->insert([
            ['id' => 1, 'hq_id' => 1, 'code' => 'DEFAULT', 'title' => 'قیف فروش', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 2, 'code' => 'DEFAULT', 'title' => 'قیف فروش', 'created_by' => 2],
        ]);
        DB::table('crm_sales_funnel_steps')->insert([
            ['id' => 1, 'hq_id' => 1, 'funnel_id' => 1, 'code' => 'NEGOTIATION', 'title' => 'مذاکره', 'sort_order' => 1, 'outcome_type' => 'OPEN', 'created_by' => 1],
            ['id' => 2, 'hq_id' => 2, 'funnel_id' => 2, 'code' => 'NEGOTIATION', 'title' => 'مذاکره', 'sort_order' => 1, 'outcome_type' => 'OPEN', 'created_by' => 2],
        ]);
        DB::table('crm_opportunities')->insert([
            ['id' => 3, 'hq_id' => 1, 'customer_id' => 11, 'funnel_id' => 1, 'current_step_id' => 1, 'assignee_id' => 1, 'created_by' => 1, 'title' => 'قرارداد سالانه'],
            ['id' => 4, 'hq_id' => 1, 'customer_id' => 12, 'funnel_id' => 1, 'current_step_id' => 1, 'assignee_id' => 1, 'created_by' => 1, 'title' => 'فرصت سرنخ'],
            ['id' => 9, 'hq_id' => 2, 'customer_id' => 99, 'funnel_id' => 2, 'current_step_id' => 2, 'assignee_id' => 2, 'created_by' => 2, 'title' => 'فرصت سازمان دیگر'],
        ]);
        DB::table('crm_tasks')->insert([
            ['id' => 5, 'hq_id' => 1, 'title' => 'پیگیری پیشنهاد', 'customer_id' => 11, 'opportunity_id' => 3, 'created_by' => 1],
            ['id' => 6, 'hq_id' => 1, 'title' => 'کار داخلی', 'customer_id' => null, 'opportunity_id' => null, 'created_by' => 1],
            ['id' => 7, 'hq_id' => 2, 'title' => 'کار سازمان دیگر', 'customer_id' => null, 'opportunity_id' => null, 'created_by' => 2],
        ]);
    }

    private function record(array $body): TestResponse
    {
        static $sequence = 0;

        return $this->withHeader('Idempotency-Key', 'record-activity-'.str_pad((string) ++$sequence, 6, '0', STR_PAD_LEFT))
            ->postJson(self::ENDPOINT, $body);
    }

    /** @return array<string, mixed> */
    private function call(): array
    {
        return [
            'type' => 'CALL', 'occurred_at' => '2026-09-29T10:00:00+03:30', 'customer_id' => 11, 'contact_customer_id' => 13,
            'direction' => 'OUTBOUND', 'contact_value' => '09121234567', 'call_outcome' => 'ANSWERED', 'duration_minutes' => 5,
            'body' => 'پیگیری پیشنهاد',
        ];
    }

    /** @return array<string, mixed> */
    private function meeting(): array
    {
        return [
            'type' => 'MEETING', 'occurred_at' => '2026-09-29T10:00:00Z', 'opportunity_id' => 3,
            'meeting_mode' => 'ONLINE', 'meeting_url' => 'https://meet.example.test/x', 'duration_minutes' => 45,
            'participants' => [['user_id' => 1, 'minutes' => 45], ['user_id' => 3, 'minutes' => 30]],
        ];
    }

    /** @param list<string> $permissions */
    private function authenticate(
        array $permissions = ['crm.activity.manage'],
        ?string $hqId = '1',
        bool $enabled = true,
        ScopeType $scope = ScopeType::TENANT,
    ): AuthenticatedPrincipal {
        $claims = new AccessTokenClaims('1', 'crm-activity-session', $hqId, false, 'crm-activity-token', time() + 3600);
        $principal = new AuthenticatedPrincipal($claims->userId, $claims->sessionId, $claims->hqId, $claims->mustChangePassword);
        $this->mock(AccessTokenServiceInterface::class, fn (MockInterface $mock) => $mock->shouldReceive('decode')->with('crm-activity')->andReturn($claims));
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
        $this->withToken('crm-activity');

        return $principal;
    }
}
