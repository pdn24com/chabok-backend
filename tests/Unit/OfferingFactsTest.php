<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\ServiceCatalog\Domain\OfferingFacts;
use PHPUnit\Framework\TestCase;

final class OfferingFactsTest extends TestCase
{
    public function test_fact_paths_preserve_existing_json_context_semantics(): void
    {
        $context = [
            'receiver' => ['city_id' => 'city'],
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
}
