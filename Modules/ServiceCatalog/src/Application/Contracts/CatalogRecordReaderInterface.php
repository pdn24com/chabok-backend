<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\CatalogRecordDetailDto;

interface CatalogRecordReaderInterface
{
    public function detail(AuthenticatedPrincipal $actor, string $resource, string $id): CatalogRecordDetailDto;
}
