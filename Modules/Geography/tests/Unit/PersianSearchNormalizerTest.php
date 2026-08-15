<?php

declare(strict_types=1);

namespace Modules\Geography\Tests\Unit;

use Modules\Geography\Domain\GeographyIds;
use Modules\Geography\Domain\PersianSearchNormalizer;
use PHPUnit\Framework\TestCase;

final class PersianSearchNormalizerTest extends TestCase
{
    public function test_it_normalizes_arabic_variants_diacritics_and_spacing(): void
    {
        $normalizer = new PersianSearchNormalizer();

        $this->assertSame('کرمان یزد', $normalizer->normalize("  كِرمان\u{200C}يزد  "));
    }

    public function test_reference_ids_are_stable_and_namespaced(): void
    {
        $this->assertSame(GeographyIds::city('10866'), GeographyIds::city('10866'));
        $this->assertNotSame(GeographyIds::city('8'), GeographyIds::province('8'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-a[0-9a-f]{3}-[0-9a-f]{12}$/', GeographyIds::city('10866'));
    }
}
