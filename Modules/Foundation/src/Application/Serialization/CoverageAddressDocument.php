<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Serialization;

use Modules\Foundation\Domain\ValueObjects\CoverageAddress;

/** Adapter boundary for geography providers that accept contact documents. */
final class CoverageAddressDocument
{
    public static function serialize(CoverageAddress $address): array
    {
        return $address->expressionFacts();
    }
}
