<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;

interface QuoteBundleStoreInterface
{
    public function put(
        string $quoteId,
        ConsignmentQuoteBundleDto $bundle,
        int $ttlSeconds,
    ): void;

    public function get(string $quoteId): ?ConsignmentQuoteBundleDto;

    public function forget(string $quoteId): void;
}
