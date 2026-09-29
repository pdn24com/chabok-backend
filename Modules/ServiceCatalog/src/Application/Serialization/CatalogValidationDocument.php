<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Domain\ValueObjects\CatalogValidationIssue;
use Modules\ServiceCatalog\Domain\ValueObjects\CatalogValidationResult;

final class CatalogValidationDocument
{
    public static function make(CatalogValidationResult $result): array
    {
        return ['valid' => $result->valid(), 'errors' => array_map(static fn (CatalogValidationIssue $issue): array => ['code' => $issue->code->value, 'field' => $issue->field], $result->errors)];
    }
}
