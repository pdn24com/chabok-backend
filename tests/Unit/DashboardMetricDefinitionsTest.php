<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Consignment\Domain\Enums\ConsignmentStatus;
use Modules\Dashboard\Domain\Definitions\DashboardMetricDefinitions;
use PHPUnit\Framework\TestCase;

final class DashboardMetricDefinitionsTest extends TestCase
{
    public function test_active_and_terminal_consignment_states_are_complete_and_disjoint(): void
    {
        $this->assertSame(
            [],
            array_intersect(
                DashboardMetricDefinitions::activeConsignmentStatuses(),
                DashboardMetricDefinitions::terminalConsignmentStatuses(),
            ),
        );
        $this->assertSame(
            DashboardMetricDefinitions::consignmentStatuses(),
            array_values(array_filter(
                DashboardMetricDefinitions::consignmentStatuses(),
                static fn (string $status): bool => DashboardMetricDefinitions::isActiveConsignmentStatus($status)
                    || in_array(
                        $status,
                        DashboardMetricDefinitions::terminalConsignmentStatuses(),
                        true,
                    ),
            )),
        );
        $this->assertTrue(DashboardMetricDefinitions::isActiveConsignmentStatus('NOK'));
        $this->assertTrue(DashboardMetricDefinitions::isActiveConsignmentStatus('RCH'));
        $this->assertFalse(DashboardMetricDefinitions::isActiveConsignmentStatus('OK'));
        $this->assertFalse(DashboardMetricDefinitions::isActiveConsignmentStatus('RO'));
        $this->assertFalse(DashboardMetricDefinitions::isActiveConsignmentStatus('AA'));
    }

    public function test_unknown_tenant_status_codes_are_not_treated_as_active(): void
    {
        $this->assertFalse(DashboardMetricDefinitions::isActiveConsignmentStatus('TENANT_CUSTOM'));
    }

    public function test_every_seeded_catalog_code_has_an_enum_case(): void
    {
        $catalog = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2).'/Modules/Consignment/resources/operational-statuses.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame(
            array_column($catalog, 'code'),
            ConsignmentStatus::values(),
        );
        foreach ($catalog as $definition) {
            $status = ConsignmentStatus::from($definition['code']);
            $this->assertSame($definition['is_terminal'], $status->isTerminal(), $definition['code']);
            $this->assertSame($definition['status_group'], $status->group()?->value, $definition['code']);
        }
    }

    public function test_open_manifest_state_is_not_draft_or_closed(): void
    {
        $this->assertSame(['DRAFT', 'OPEN', 'CLOSED'], DashboardMetricDefinitions::manifestStates());
        $this->assertSame('OPEN', DashboardMetricDefinitions::manifestStates()[1]);
    }

    public function test_manifest_target_statuses_match_the_transitions_a_node_can_raise(): void
    {
        $this->assertSame(['IR', 'OF', 'OD'], DashboardMetricDefinitions::manifestTargetStatuses());
    }
}
