<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\CatalogRecordDetailDto;

interface CatalogRecordChangeRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $id, string $correlation, string $action, ?CatalogRecordDetailDto $before, CatalogRecordDetailDto $after): void;
}
