<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CatalogRecordReader
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordAccessGuard $catalogRecordAccessGuard,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRecordRepository $records,
        private \Modules\ServiceCatalog\Application\CommitmentScheduleService $schedules,
        private \Modules\ServiceCatalog\Application\ServiceCatalogService $catalog,
    )
    {
    }

    public function detail(AuthenticatedPrincipal $actor, string $resource, string $id): array
    {
        $this->catalogRecordAccessGuard->authorize($actor);
        [$identityId, $versionId] = \Modules\ServiceCatalog\Domain\CatalogResource::keys($resource);
        $identity = $this->records->identity($actor->hqId, $resource, $id);
        if ($identity === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $revisionId = (string) $this->records->latestRevisionId($resource, $id);
        $detail = $resource === 'commitment-schedules' ? $this->schedules->versionDetail($actor, $revisionId) : $this->catalog->versionDetail($actor, $resource, $revisionId);
        if ($resource === 'offerings') {
            foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $dependency) {
                [$stable] = \Modules\ServiceCatalog\Domain\CatalogResource::keys($dependency);
                $detail[$stable] = $this->records->identityForRevision($dependency, $detail[$field]);
            }
            if (!empty($detail['commitment_binding'])) {
                $detail['commitment_binding']['commitment_schedule_id'] = $this->records->identityForRevision('commitment-schedules', $detail['commitment_binding']['commitment_schedule_version_id']);
            }
            foreach ($detail['option_rules'] ?? [] as $index => $rule) {
                $detail['option_rules'][$index]['service_option_id'] = $this->records->identityForRevision('options', $rule['service_option_version_id']);
            }
        }
        return [...$detail, 'status' => $identity->status, 'lock_version' => (int) $identity->edit_lock];
    }
}
