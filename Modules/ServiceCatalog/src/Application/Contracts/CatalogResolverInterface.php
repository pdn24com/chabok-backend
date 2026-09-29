<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\OptionRevisionSetDto;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

interface CatalogResolverInterface
{
    /** @param list<string> $references @return list<OptionRevisionSetDto> */
    public function optionRevisions(array $references, ?string $hqId = null): array;

    /** @return list<string> */
    public function relatedVersions(CatalogResource $resource, string $reference): array;
}
