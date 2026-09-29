<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

interface PricingReaderInterface
{
    public function tariffVersion(AuthenticatedPrincipal $actor, string $versionId): TariffVersionRecord;

    public function zoneVersion(AuthenticatedPrincipal $actor, string $versionId): PricingZoneSetVersionRecord;

    /** @param list<string> $ids @return Collection<int, TariffVersionRecord> */
    public function tariffVersions(AuthenticatedPrincipal $actor, array $ids): Collection;

    /** @param list<string> $ids @return Collection<int, PricingZoneSetVersionRecord> */
    public function zoneVersions(AuthenticatedPrincipal $actor, array $ids): Collection;

    public function snapshotDetail(string $snapshotId): PricingSnapshotRecord;

    public function quoteDetail(AuthenticatedPrincipal $actor, string $quoteId): PricingQuoteRecord;
}
