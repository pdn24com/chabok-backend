<?php

declare(strict_types=1);

namespace Modules\Geography\Tests\Unit;

use Modules\Geography\Domain\Support\PersianSearchNormalizer;
use PHPUnit\Framework\TestCase;

final class PersianSearchNormalizerTest extends TestCase
{
    public function test_it_normalizes_arabic_variants_diacritics_and_spacing(): void
    {
        $normalizer = new PersianSearchNormalizer;

        $this->assertSame('کرمان یزد', $normalizer->normalize("  كِرمان\u{200C}يزد  "));
    }
}
