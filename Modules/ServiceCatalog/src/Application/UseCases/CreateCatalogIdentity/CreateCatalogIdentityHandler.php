<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateCatalogIdentityHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition $catalogResourceDefinition,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\ServiceCatalog\Application\CatalogCode $codes,
        private \Modules\ServiceCatalog\Application\Services\CatalogInput $catalogInput,
        private \Modules\ServiceCatalog\Application\Services\OfferingChildrenWriter $offeringChildrenWriter,
        private \Modules\ServiceCatalog\Application\Services\CatalogChangeRecorder $catalogChangeRecorder,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
    )
    {
    }

    public function handle(CreateCatalogIdentityCommand $command): CreateCatalogIdentityResult
    {
        return new CreateCatalogIdentityResult($this->execute($command->actor, $command->resource, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $resource, array $input, string $correlationId): array
    {
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if ($resource === 'offerings' && array_key_exists('availability_bindings', $input)) {
            $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.availability.manage');
        }
        [$identityId, $versionId] = $this->catalogResourceDefinition->map($resource);
        return $this->transactions->run(function () use ($actor, $resource, $input, $correlationId, $identityId, $versionId): array {
            $id = $this->identifiers->uuid();
            $draftId = $this->identifiers->uuid();
            $now = $this->clock->now();
            $this->catalog->insertIdentity($resource, [
                $identityId => $id,
                'hq_id' => $actor->hqId,
                'owner_key' => (string) $actor->hqId,
                'code' => !empty($input['code']) ? mb_strtoupper((string) $input['code']) : $this->codes->generate($resource, (string) $actor->hqId),
                'status' => 'ACTIVE',
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $version = $this->catalogInput->versionColumns($resource, $input) + [
                $versionId => $draftId,
                $identityId => $id,
                'hq_id' => $actor->hqId,
                'version_number' => 1,
                'previous_version_id' => null,
                'status' => 'DRAFT',
                'lock_version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->catalog->insertVersion($resource, $version);
            if ($resource === 'offerings') {
                $this->offeringChildrenWriter->replaceOfferingChildren($draftId, $input, $actor->hqId);
            }
            $this->catalogChangeRecorder->record($actor, 'SERVICE_CATALOG_IDENTITY_CREATED', mb_strtoupper(str_replace('-', '_', $resource)), $id, $correlationId, ['version_id' => $draftId]);
            return $this->catalogReader->versionDetail($actor, $resource, $draftId);
        });
    }
}
