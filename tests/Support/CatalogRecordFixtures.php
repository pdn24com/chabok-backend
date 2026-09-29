<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordReaderInterface;
use Modules\ServiceCatalog\Application\Mappers\CatalogDraftInput;
use Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordCommand;
use Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordHandler;
use Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveCommand;
use Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveHandler;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogRecordResource;

final readonly class CatalogRecordFixtures
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CatalogRecordReaderInterface $catalogRecordReader,
        private SaveCatalogRecordHandler $saveCatalogRecord,
        private SetCatalogRecordActiveHandler $setCatalogRecordActive,
    ) {}

    public function authorize(AuthenticatedPrincipal $actor, bool $write = false): void
    {
        $this->catalogAccessGuard->authorizeRecord($actor, $write);
    }

    public function detail(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $id,
    ): array {
        return (new CatalogRecordResource($this->catalogRecordReader->detail($actor, $resource, $id)))->resolve();
    }

    public function save(
        AuthenticatedPrincipal $actor,
        string $resource,
        ?string $id,
        array $input,
        string $correlation,
    ): array {
        return (new CatalogRecordResource($this->saveCatalogRecord->handle(new SaveCatalogRecordCommand($actor, $resource, $id, CatalogDraftInput::record($resource, $input), $correlation))))->resolve();
    }

    public function setActive(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $id,
        bool $active,
        int $expected,
        string $correlation,
    ): array {
        return (new CatalogRecordResource($this->setCatalogRecordActive->handle(new SetCatalogRecordActiveCommand($actor, $resource, $id, $active, $expected, $correlation))))->resolve();
    }
}
