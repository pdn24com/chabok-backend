<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

/** Participates in the caller's aggregate transaction; does not begin or commit one. */

interface ConsignmentQuoteAcceptance
{
    public function acceptForConsignment(string $hqId, string $consignmentId, string $actorId, int $version, array $accepted): string;
}
