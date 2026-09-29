<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Domain\Support\OfferingFacts;
use PHPUnit\Framework\TestCase;

final class OfferingFactsTest extends TestCase
{
    public function test_fact_paths_preserve_existing_json_context_semantics(): void
    {
        $context = [
            'receiver' => ['city_id' => '18506435'],
            'parcels' => [['weight' => 2], ['weight' => null]],
            'groups' => [['rows' => [['code' => 'A']]], ['rows' => [['code' => 'B']]]],
            '*' => 'literal',
        ];
        foreach ([
            'receiver.city_id',
            'receiver.missing',
            'parcels.*.weight',
            'parcels.{first}.weight',
            'parcels.{last}.weight',
            'groups.*.rows.*.code',
            '\*',
            'receiver.city_id.*',
            '',
        ] as $path) {
            self::assertSame(data_get($context, $path), OfferingFacts::value($context, $path), $path);
        }
    }

    public function test_typed_selection_keeps_configured_fact_paths_and_wire_order(): void
    {
        $wire = ['flag' => false, 'sender' => ['city_id' => '18506435', 'latitude' => '35.00', 'custom' => ['x' => 1, 'y' => 2]],
            'selected_option_version_ids' => ['212432914', '65158786'], 'receiver' => ['postal_code' => '0012345678'],
            'parcels' => [['weight_kg' => 2], ['weight_kg' => 3]], 'acceptance_at' => '2026-09-26T10:00:00Z'];
        $context = OfferingSelectionInput::fromArray($wire);
        foreach (['flag', 'sender.city_id', 'sender.latitude', 'sender.custom.*', 'sender.{first}', 'sender.{last}',
            'selected_option_version_ids.*', 'receiver.postal_code', 'parcels.*.weight_kg', '{first}', '{last}', 'missing'] as $path) {
            self::assertSame(data_get($wire, $path), OfferingFacts::value($context, $path), $path);
        }
    }
}
