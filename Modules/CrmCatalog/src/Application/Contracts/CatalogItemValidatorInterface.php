<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Contracts;

use Modules\CrmCatalog\Application\Dto\CatalogItemChangesDto;
use Modules\CrmCatalog\Application\Dto\CatalogItemDraftDto;

interface CatalogItemValidatorInterface
{
    /** Judges the references of a new item: category, persona and sales model active in the tenant, industries active and named once. */
    public function validate(string $hqId, CatalogItemDraftDto $draft): void;

    /**
     * Judges only what a PATCH moves. A reference the item already holds is left alone even when it has
     * been retired since, so an unrelated edit is never refused because of old data.
     */
    public function validateChanges(string $hqId, CatalogItemChangesDto $changes, CatalogItemDraftDto $current): void;
}
