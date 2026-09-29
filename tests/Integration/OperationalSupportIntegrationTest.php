<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use Modules\Foundation\Application\Contracts\SecurityMetricRecorderInterface;
use Modules\Foundation\Application\Dto\OutboxEventDto;
use Modules\Foundation\Application\Dto\PublicationReceiptDto;
use Modules\Foundation\Application\Exceptions\OutboxPublishException;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxEventPublisherInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Iam\Application\UseCases\CreateInvitation\CreateInvitationCommand;
use Modules\Iam\Application\UseCases\CreateInvitation\CreateInvitationHandler;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Modules\Outbox\Application\UseCases\PublishPendingEvents\PublishPendingEventsCommand;
use Modules\Outbox\Application\UseCases\PublishPendingEvents\PublishPendingEventsHandler;
use Modules\Outbox\Application\UseCases\ReplayEvent\ReplayEventCommand;
use Modules\Outbox\Application\UseCases\ReplayEvent\ReplayEventHandler;
use Tests\Support\RecordFixtureQuery;

final class OperationalSupportIntegrationTest extends MySqlRedisTestCase
{
    public function test_audit_events_are_database_enforced_append_only_and_redacted(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant['hq_id'], 'audit-user');
        $this->app->make(AuditWriterInterface::class)->write(
            $tenant['hq_id'],
            $user['user_id'],
            'SECURITY_TEST',
            'USER',
            $user['user_id'],
            '1178652326',
            after: ['password' => 'NeverPersistThis', 'status' => 'ACTIVE'],
        );
        $row = RecordFixtureQuery::table('audit_events')->where('action_key', 'SECURITY_TEST')->first();
        $this->assertNotNull($row);
        $this->assertStringNotContainsString('NeverPersistThis', (string) $row->after_snapshot);
        $this->assertStringContainsString('[REDACTED]', (string) $row->after_snapshot);

        try {
            RecordFixtureQuery::table('audit_events')->where('audit_id', $row->audit_id)->update(['safe_note' => 'changed']);
            $this->fail('Audit update should be rejected by MySQL.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
        try {
            RecordFixtureQuery::table('audit_events')->where('audit_id', $row->audit_id)->delete();
            $this->fail('Audit delete should be rejected by MySQL.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }
        $this->assertDatabaseHasPublic('audit_events', ['audit_id' => $row->audit_id]);
    }

    public function test_worker_claims_publishes_generic_events_and_updates_readiness_heartbeat(): void
    {
        $tenant = $this->tenant();
        $eventId = $this->event($tenant['hq_id'], 'iam.user.created', [
            'user_id' => (string) random_int(1, 2000000000),
            'status' => 'ACTIVE',
            'creation_mode' => 'DIRECT_ACTIVE',
        ]);

        $result = $this->app->make(PublishPendingEventsHandler::class)->handle(new PublishPendingEventsCommand(10, 'test-worker'));

        $this->assertSame([1, 1, 0, 0], [$result->claimed, $result->published, $result->failed, $result->deadLettered]);
        $this->assertDatabaseHasPublic('outbox_events', [
            'event_id' => $eventId,
            'publication_state' => 'PUBLISHED',
            'attempts' => 1,
            'claim_token' => null,
        ]);
        $this->assertNotNull(Redis::connection('cache')->get('chabok:outbox:heartbeat'));
        $this->getJson('/health/ready')->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('components.mysql', 'UP')
            ->assertJsonPath('components.redis', 'UP')
            ->assertJsonPath('components.outbox_worker', 'UP');
    }

    public function test_outbox_payload_schema_rejects_missing_fields_before_persistence(): void
    {
        $tenant = $this->tenant();

        try {
            $this->app->make(OutboxWriterInterface::class)->write(
                $tenant['hq_id'],
                'USER',
                (string) random_int(1, 2000000000),
                'iam.user.created',
                (string) random_int(1, 2000000000),
                ['user_id' => (string) random_int(1, 2000000000)],
            );
            $this->fail('Incomplete event payload should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('payload shape', $exception->getMessage());
        }
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_otp_and_invitation_delivery_are_idempotent_and_destroy_encrypted_secrets(): void
    {
        $tenant = $this->tenant();
        $user = $this->user($tenant['hq_id'], 'delivery-user');
        RecordFixtureQuery::table('users')->where('user_id', $user['user_id'])->update([
            'email' => 'delivery@example.com',
            'normalized_email' => 'delivery@example.com',
        ]);
        $otp = $this->postJson('/api/v1/auth/otp/send', [
            'identifier' => 'delivery-user',
            'purpose' => 'PASSWORD_RESET',
        ])->assertOk();
        $this->app->make(CreateInvitationHandler::class)->handle(new CreateInvitationCommand(
            UserRecord::query()->where('user_id', $user['user_id'])->firstOrFail(),
            'EMAIL',
            null,
            '1025467437',
        ));
        $events = RecordFixtureQuery::table('outbox_events')->whereIn('event_type', [
            'identity.otp.delivery.requested',
            'identity.invitation.delivery.requested',
        ])->get();
        $rawSecrets = $events->map(function ($event): string {
            $payload = json_decode((string) $event->payload, true, 512, JSON_THROW_ON_ERROR);

            return Crypt::decryptString($payload['delivery_ciphertext']);
        })->all();

        $result = $this->app->make(PublishPendingEventsHandler::class)->handle(new PublishPendingEventsCommand(10, 'notification-worker'));

        $this->assertSame(2, $result->published);
        $this->assertSame(2, RecordFixtureQuery::table('notification_deliveries')->count());
        $this->assertSame(1, RecordFixtureQuery::table('notification_deliveries')
            ->where('event_id', $events[0]->event_id)->count());
        foreach ($events as $event) {
            $payload = json_decode((string) RecordFixtureQuery::table('outbox_events')
                ->where('event_id', $event->event_id)->value('payload'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayNotHasKey('delivery_ciphertext', $payload);
            $this->assertArrayHasKey('delivery_secret_destroyed_at', $payload);
        }
        $persisted = json_encode([
            RecordFixtureQuery::table('outbox_events')->get(),
            RecordFixtureQuery::table('notification_deliveries')->get(),
        ], JSON_THROW_ON_ERROR);
        foreach ($rawSecrets as $secret) {
            $this->assertStringNotContainsString($secret, $persisted);
        }
        $this->assertSame($otp->json('data.challenge_id'), $events
            ->firstWhere('event_type', 'identity.otp.delivery.requested')->aggregate_id);
    }

    public function test_failures_back_off_dead_letter_audit_and_controlled_replay(): void
    {
        config()->set('chabok.outbox.max_attempts', 2);
        config()->set('chabok.outbox.base_backoff_seconds', 7);
        $this->app->instance(OutboxEventPublisherInterface::class, new class implements OutboxEventPublisherInterface
        {
            public function publish(OutboxEventDto $event): PublicationReceiptDto
            {
                throw new OutboxPublishException('TEST_PROVIDER_DOWN');
            }
        });
        $processor = $this->app->make(PublishPendingEventsHandler::class);
        $tenant = $this->tenant();
        $eventId = $this->event($tenant['hq_id'], 'iam.role.updated', [
            'role_id' => (string) random_int(1, 2000000000),
        ]);

        $first = $processor->handle(new PublishPendingEventsCommand(1, 'failure-worker'));
        $this->assertSame(1, $first->failed);
        $firstRow = RecordFixtureQuery::table('outbox_events')->where('event_id', $eventId)->first();
        $this->assertSame('FAILED', $firstRow->publication_state);
        $this->assertSame(1, (int) $firstRow->attempts);
        $this->assertGreaterThanOrEqual(6, strtotime((string) $firstRow->next_attempt_at) - time());

        RecordFixtureQuery::table('outbox_events')->where('event_id', $eventId)->update([
            'next_attempt_at' => now()->subSecond(),
        ]);
        $second = $processor->handle(new PublishPendingEventsCommand(1, 'failure-worker'));
        $this->assertSame(1, $second->deadLettered);
        $this->assertDatabaseHasPublic('outbox_events', [
            'event_id' => $eventId,
            'publication_state' => 'DEAD_LETTER',
            'attempts' => 2,
            'last_failure_code' => 'TEST_PROVIDER_DOWN',
        ]);
        $this->assertDatabaseHasPublic('audit_events', [
            'action_key' => 'OUTBOX_EVENT_DEAD_LETTERED',
            'target_id' => $eventId,
        ]);
        $this->app->make(ReplayEventHandler::class)->handle(new ReplayEventCommand($eventId));
        $this->assertDatabaseHasPublic('outbox_events', [
            'event_id' => $eventId,
            'publication_state' => 'PENDING',
            'attempts' => 0,
            'last_failure_code' => null,
        ]);
    }

    public function test_stale_claim_is_recovered_and_notification_consumer_is_event_idempotent(): void
    {
        $tenant = $this->tenant();
        $eventId = $this->event($tenant['hq_id'], 'iam.role.updated', [
            'role_id' => (string) random_int(1, 2000000000),
        ]);
        RecordFixtureQuery::table('outbox_events')->where('event_id', $eventId)->update([
            'claim_token' => (string) random_int(1, 2000000000),
            'claimed_by' => 'dead-worker',
            'claimed_at' => now()->subMinutes(10),
        ]);

        $result = $this->app->make(PublishPendingEventsHandler::class)->handle(new PublishPendingEventsCommand(1, 'recovery-worker'));

        $this->assertSame(1, $result->published);
        $this->assertDatabaseHasPublic('outbox_events', [
            'event_id' => $eventId,
            'publication_state' => 'PUBLISHED',
            'attempts' => 1,
            'claimed_by' => null,
        ]);
    }

    public function test_safe_security_metrics_record_auth_and_origin_failures_without_identifiers(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'metric-secret-user',
            'password' => 'metric-secret-password',
        ])->assertStatus(401);
        $this->withHeader('Origin', 'https://foreign.example')
            ->postJson('/api/v1/auth/refresh')->assertStatus(403);
        $metrics = $this->app->make(SecurityMetricRecorderInterface::class);

        $this->assertSame(1, $metrics->snapshot('auth.invalid_credentials')['client=BRANCH_PANEL']);
        $this->assertSame(1, $metrics->snapshot('auth.origin_rejected')['reason=UNLISTED']);
        $serialized = json_encode([
            $metrics->snapshot('auth.invalid_credentials'),
            $metrics->snapshot('auth.origin_rejected'),
        ], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('metric-secret-user', $serialized);
        $this->assertStringNotContainsString('metric-secret-password', $serialized);
    }

    /** @param array<string, mixed> $payload */
    private function event(string $hqId, string $type, array $payload): string
    {
        $aggregateId = (string) random_int(1, 2000000000);
        $this->app->make(OutboxWriterInterface::class)->write(
            $hqId,
            'TEST',
            $aggregateId,
            $type,
            (string) random_int(1, 2000000000),
            $payload,
        );

        return (string) RecordFixtureQuery::table('outbox_events')
            ->where('aggregate_id', $aggregateId)->value('event_id');
    }
}
