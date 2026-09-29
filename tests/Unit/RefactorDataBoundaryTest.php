<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Application\Mappers\ConsignmentQuoteMapper;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Consignment\Application\Serialization\ConsignmentQuoteDocument;
use Modules\Consignment\Domain\Support\InputFingerprint;
use Modules\Manifest\Application\Serialization\ManifestTimestamp;
use Modules\Pricing\Application\Mappers\QuoteInputMapper;
use Modules\Pricing\Application\Serialization\QuoteInputDocument;
use Modules\ServiceCatalog\Application\Mappers\CatalogDraftInput;
use Modules\ServiceCatalog\Application\Services\CurrentCatalog;
use PHPUnit\Framework\TestCase;

final class RefactorDataBoundaryTest extends TestCase
{
    public function test_typed_quote_inputs_preserve_null_omission_and_numeric_wire_fingerprints(): void
    {
        $draft = ['sender' => ['city_id' => '25296341', 'latitude' => '35.0000'], 'receiver' => ['city_id' => '190608731', 'phone' => null],
            'weight_kg' => '1.20', 'declared_value_amount' => '1000', 'insurance_enabled' => true, 'cod_enabled' => false,
            'parcels' => [['weight_kg' => 1.2, 'width_cm' => null, 'content_description' => null]]];
        $consignment = ConsignmentDraftDocument::draft(ConsignmentInputMapper::draft($draft));
        self::assertSame(InputFingerprint::of($draft), InputFingerprint::of($consignment));
        self::assertArrayNotHasKey('length_cm', $consignment['parcels'][0]);
        self::assertArrayHasKey('width_cm', $consignment['parcels'][0]);
        self::assertSame('1.20', $consignment['weight_kg']);
        $pricing = QuoteInputDocument::quote(QuoteInputMapper::quote($draft));
        self::assertSame(InputFingerprint::of($draft), InputFingerprint::of($pricing));
        self::assertSame('35.0000', $pricing['sender']['latitude']);
    }

    public function test_existing_quote_cache_documents_round_trip_without_losing_private_or_provider_evidence(): void
    {
        $bundle = ['quote_id' => '103969350', 'quote_version' => 1, 'hq_id' => '245213294', 'node_id' => '88468052', 'purpose' => 'CREATE',
            'input_fingerprint' => 'input', 'consignment_id' => null, 'expected_version' => null,
            'provider_calculated_at' => '2026-09-26T00:00:00Z', 'expires_at' => '2026-09-26T00:10:00Z',
            'options' => [['option_id' => '168929119', '_resolved_input_fingerprint' => 'resolved', 'available' => true,
                'external_method_code' => '7', 'method_name' => 'Legacy', 'currency' => 'IRR', 'total_amount' => 2500,
                'charge_lines' => [['charge_code' => 'BASE', 'title' => 'Base', 'amount' => 2500]],
                'delivery_windows' => [['gregorian_date' => '2026-10-01', 'time_ranges' => ['09:00 - 11:00']]], 'icon' => null]]];
        $typed = ConsignmentQuoteMapper::bundle($bundle);
        self::assertSame('resolved', $typed->options[0]->resolvedInputFingerprint);
        self::assertSame(2500, $typed->options[0]->chargeLines[0]->amount);
        self::assertSame(InputFingerprint::of($bundle), InputFingerprint::of(ConsignmentQuoteDocument::bundle($typed)));
    }

    public function test_catalog_record_fingerprints_remain_compatible_before_default_normalization(): void
    {
        $inputs = [
            'offerings' => ['code' => 'TEST', 'expected_version' => 3, 'labels' => ['fa' => 'آزمایش'],
                'service_type_version_id' => '19938311', 'shipping_method_version_id' => '95938240',
                'availability_bindings' => [['scope_type' => 'TENANT']],
                'eligibility_rules' => [['dimension' => 'PARCEL', 'fact_key' => 'weight_kg', 'operator' => 'MIN', 'expected_value' => '1.00', 'reason_code' => 'TOO_LIGHT', 'priority' => '10']]],
            'commitment-schedules' => ['code' => 'SCHEDULE', 'title' => 'Schedule', 'expected_version' => 2,
                'windows' => [['window_code' => 'AM', 'window_type' => 'PICKUP', 'label_fa' => 'صبح', 'start_time' => '09:00', 'end_time' => '12:00', 'booking_cutoff_time' => '08:00', 'applicable_weekdays' => [1, 2]]]],
        ];
        foreach ($inputs as $resource => $input) {
            $old = [...$input, 'valid_from' => null, 'valid_to' => null];
            unset($old['code'], $old['expected_version']);
            self::assertSame(InputFingerprint::of($old), CurrentCatalog::fingerprint(CatalogDraftInput::record($resource, $input)));
        }
    }

    public function test_manifest_timestamps_keep_the_existing_model_and_raw_string_precision(): void
    {
        $modelTimestamp = CarbonImmutable::parse('2026-09-26T10:11:12.123456Z');
        self::assertSame('2026-09-26T10:11:12.000000Z', ManifestTimestamp::format($modelTimestamp));
        self::assertSame('2026-09-26T10:11:12.123456Z', ManifestTimestamp::format('2026-09-26 10:11:12.123456'));
    }
}
