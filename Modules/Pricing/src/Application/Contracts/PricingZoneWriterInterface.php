<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

interface PricingZoneWriterInterface
{
    public function replaceZones(string $versionId, array $zones, ?string $sourceVersionId = null): void;
}
