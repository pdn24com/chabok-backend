<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Dashboard\Domain\DashboardMetricDefinitions;
use PHPUnit\Framework\TestCase;

final class DashboardMetricDefinitionsTest extends TestCase
{
    public function test_active_and_terminal_consignment_states_are_complete_and_disjoint(): void
    {
        $this->assertSame(
            [],
            array_intersect(
                DashboardMetricDefinitions::ACTIVE_CONSIGNMENT_STATUSES,
                DashboardMetricDefinitions::TERMINAL_CONSIGNMENT_STATUSES,
            ),
        );
        $this->assertSame(
            DashboardMetricDefinitions::CONSIGNMENT_STATUSES,
            array_values(array_filter(
                DashboardMetricDefinitions::CONSIGNMENT_STATUSES,
                static fn (string $status): bool =>
                    DashboardMetricDefinitions::isActiveConsignmentStatus($status)
                    || in_array(
                        $status,
                        DashboardMetricDefinitions::TERMINAL_CONSIGNMENT_STATUSES,
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

    public function test_open_manifest_state_is_not_draft_or_closed(): void
    {
        $this->assertSame(['DRAFT', 'OPEN', 'CLOSED'], DashboardMetricDefinitions::MANIFEST_STATES);
        $this->assertSame('OPEN', DashboardMetricDefinitions::MANIFEST_STATES[1]);
    }
}
