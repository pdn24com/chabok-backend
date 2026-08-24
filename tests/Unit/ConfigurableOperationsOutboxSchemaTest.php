<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Outbox\Application\OutboxEventSchemaRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigurableOperationsOutboxSchemaTest extends TestCase
{
    #[Test]
    public function it_accepts_network_and_fleet_configuration_events(): void
    {
        $registry = new OutboxEventSchemaRegistry();

        $registry->assertValid('network.configuration.changed', 1, [
            'action' => 'network.node.updated',
            'target_type' => 'NODE',
            'target_id' => '00000000-0000-4000-8000-000000000001',
        ]);
        $registry->assertValid('network.configuration.changed', 1, [
            'action' => 'network.route.published',
            'target_type' => 'ROUTE_DEFINITION_VERSION',
            'target_id' => '00000000-0000-4000-8000-000000000002',
            'status' => 'PUBLISHED',
        ]);
        $registry->assertValid('fleet.configuration.changed', 1, [
            'action' => 'fleet.vehicle.updated',
            'target_type' => 'VEHICLE',
            'target_id' => '00000000-0000-4000-8000-000000000003',
            'status' => 'ACTIVE',
        ]);

        self::assertTrue(true);
    }

    #[Test]
    public function it_accepts_the_exact_wave2_operational_command_envelope(): void
    {
        $registry = new OutboxEventSchemaRegistry();
        $payload = [
            'command' => 'DELIVERY_TASK_RETRIED',
            'resource_id' => '00000000-0000-4000-8000-000000000101',
            'consignment_id' => '00000000-0000-4000-8000-000000000102',
            'status' => 'PENDING',
        ];

        $registry->assertValid('operations.command.executed', 1, $payload);

        $this->expectException(\InvalidArgumentException::class);
        $registry->assertValid('operations.command.executed', 1, [
            ...$payload,
            'unrestricted_metadata' => ['unsafe' => true],
        ]);
    }

    #[Test]
    public function it_rejects_incomplete_or_versionless_wave2_operational_events(): void
    {
        $registry = new OutboxEventSchemaRegistry();

        try {
            $registry->assertValid('operations.command.executed', 1, [
                'command' => 'MANIFEST_OS_CONFIRMED',
                'resource_id' => '00000000-0000-4000-8000-000000000201',
                'consignment_id' => '00000000-0000-4000-8000-000000000202',
            ]);
            self::fail('Missing status must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }

        $this->expectException(\InvalidArgumentException::class);
        $registry->assertValid('operations.command.executed', 2, [
            'command' => 'MANIFEST_OS_CONFIRMED',
            'resource_id' => '00000000-0000-4000-8000-000000000201',
            'consignment_id' => '00000000-0000-4000-8000-000000000202',
            'status' => 'OS',
        ]);
    }
}
