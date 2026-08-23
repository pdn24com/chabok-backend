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
}
