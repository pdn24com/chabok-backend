<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Manifest\Application\Dto\ManifestContextInputDto;
use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Manifest\Domain\Enums\ManifestType;
use Modules\Manifest\Domain\Exceptions\ManifestRuleViolation;
use Modules\Manifest\Domain\Policies\ManifestPolicy;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use PHPUnit\Framework\TestCase;

final class ManifestPolicyTest extends TestCase
{
    public function test_transition_contract_preserves_all_sources_and_rejects_custom_target_codes(): void
    {
        $expected = ['PD' => ['CFM'], 'PU' => ['PD'], 'NPU' => ['PD'], 'IR' => ['PU', 'OS'], 'ROU' => ['IR'], 'OF' => ['ROU', 'CI'], 'OS' => ['OF'], 'CI' => ['OS'], 'OD' => ['IR'], 'OK' => ['OD'], 'NOK' => ['OD']];
        $policy = new ManifestPolicy;
        self::assertSame(array_keys($expected), array_column(ManifestTransition::cases(), 'value'));
        foreach ($expected as $target => $sources) {
            self::assertSame($sources, $policy->sourceStatuses($target));
            foreach (['CFM', ...array_keys($expected), 'CUSTOM'] as $source) {
                self::assertSame(in_array($source, $sources, true), $policy->canTransition($source, $target));
            }
        }
        self::assertFalse($policy->canTransition('IR', 'CUSTOM'));
        self::assertSame([ManifestContextType::PickupReception, ManifestContextType::MovementReception], ManifestTransition::Reception->contextTypes());
        self::assertSame(ManifestType::InboundReception, ManifestTransition::Reception->manifestType());
        try {
            $policy->assertContext('CUSTOM', null);
            self::fail('Unsupported transition accepted.');
        } catch (ManifestRuleViolation $error) {
            self::assertSame(ApiErrorCode::UnsupportedManifestTransition, $error->errorCode);
        }
    }

    public function test_policy_exposes_business_failures_without_http_transport(): void
    {
        $policy = new ManifestPolicy;
        $policy->assertEditable('OPEN');
        $policy->assertVersion(2, 2);
        foreach (['PD', 'OS', 'OD'] as $target) {
            try {
                $policy->assertContext($target, null);
                self::fail('Missing driver accepted.');
            } catch (ManifestRuleViolation $error) {
                self::assertSame(ApiErrorCode::ValidationError, $error->errorCode);
                self::assertSame(['assigned_driver_id' => ['manifest.assigned_driver_is_required']], $error->fieldErrors);
            }
        }
        try {
            $policy->assertEditable('CLOSED');
            self::fail('Closed manifest accepted.');
        } catch (ManifestRuleViolation $error) {
            self::assertSame(ApiErrorCode::ManifestNotEditable, $error->errorCode);
        }
        try {
            $policy->assertVersion(3, 2);
            self::fail('Stale version accepted.');
        } catch (ManifestRuleViolation $error) {
            self::assertSame(ApiErrorCode::ManifestVersionConflict, $error->errorCode);
            self::assertSame(['current_version' => 3], $error->details);
        }
    }

    public function test_context_patch_distinguishes_absent_driver_and_vehicle_from_explicit_null(): void
    {
        $manifest = (new ManifestRecord)->forceFill(['manifest_status' => 'OS', 'context_key' => 'context', 'destination_node_id' => '88468052', 'assigned_driver_id' => '189656963', 'assigned_vehicle_id' => '188763860']);
        $kept = ManifestContextInputDto::fromArray(['expected_version' => 2, 'context_key' => 'changed'])->withDefaults($manifest);
        self::assertSame('189656963', $kept->assignedDriverId);
        self::assertSame('188763860', $kept->assignedVehicleId);
        self::assertSame('changed', $kept->contextKey);
        $cleared = ManifestContextInputDto::fromArray(['expected_version' => 2, 'assigned_driver_id' => null, 'assigned_vehicle_id' => null])->withDefaults($manifest);
        self::assertNull($cleared->assignedDriverId);
        self::assertNull($cleared->assignedVehicleId);
        self::assertTrue($cleared->driverProvided);
        self::assertTrue($cleared->vehicleProvided);
        self::assertSame('context', $cleared->contextKey);
        self::assertSame('88468052', $cleared->targetNodeId);
    }
}
