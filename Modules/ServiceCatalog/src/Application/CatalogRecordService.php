<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CatalogRecordService
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordAccessGuard $catalogRecordAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordReader $catalogRecordReader,
        private \Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordHandler $saveCatalogRecord,
        private \Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveHandler $setCatalogRecordActive,
    )
    {
    }

    public function authorize(AuthenticatedPrincipal $actor, bool $write = false): void
    {
        $this->catalogRecordAccessGuard->authorize($actor, $write);
    }

    public function detail(AuthenticatedPrincipal $actor, string $resource, string $id): array
    {
        return $this->catalogRecordReader->detail($actor, $resource, $id);
    }

    public function save(AuthenticatedPrincipal $actor, string $resource, ?string $id, array $input, string $correlation): array
    {
        return $this->saveCatalogRecord->handle(new \Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordCommand($actor, $resource, $id, $input, $correlation))->data;
    }

    public function setActive(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $id,
        bool $active,
        int $expected,
        string $correlation,
    ): array
    {
        return $this->setCatalogRecordActive->handle(new \Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveCommand($actor, $resource, $id, $active, $expected, $correlation))->data;
    }
}
