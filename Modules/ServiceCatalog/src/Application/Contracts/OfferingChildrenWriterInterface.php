<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;

interface OfferingChildrenWriterInterface
{
    public function replaceOfferingChildren(string $versionId, CatalogDraftDto $input, ?string $hqId): void;

    public function cloneOfferingChildren(string $from, string $to): void;
}
