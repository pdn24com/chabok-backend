<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Contracts\ManifestConsignmentAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestContextProjectionInterface;
use Modules\Manifest\Application\Contracts\ManifestEligibilityEvaluatorInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestCountsDto;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Dto\ManifestListItemDto;
use Modules\Manifest\Application\Repositories\ManifestEvidenceRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionHandler;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class ManifestReader implements ManifestReaderInterface
{
    public function __construct(
        private ManifestContextProjectionInterface $manifestContextProjection,
        private ManifestEligibilityEvaluatorInterface $manifestEligibilityEvaluator,
        private GetManifestExceptionHandler $getManifestExceptionHandler,
        private ManifestConsignmentAccessInterface $manifestConsignmentAccess,
        private ManifestRepositoryInterface $manifestRepository,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
        private ManifestEvidenceRepositoryInterface $manifestEvidenceRepository,
    ) {}

    public function listItem(ManifestRecord|ManifestListItemDto $row): ManifestListItemDto
    {
        if ($row instanceof ManifestListItemDto) {
            return $row;
        }

        return new ManifestListItemDto($row, $this->counts($row->manifest_id), $this->manifestContextProjection->summaries($row->hq_id, [$row])[$row->manifest_id]);
    }

    public function detail(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        AccessContextDto $context,
    ): ManifestDetailDto {
        $manifest = $this->visible($actor, $nodeId, $id);
        $base = $this->listItem($manifest);
        $rows = $this->manifestParcelRepository->rowsWithConsignment($actor->hqId, $id);
        $unchecked = $rows->filter(fn ($row) => $row->failure_code === null && $row->manifest_parcel_status !== 'SUCCEEDED');
        $eligibilities = $this->manifestEligibilityEvaluator->evaluateMany(new Collection($unchecked->pluck('parcel')->all()), $manifest, $nodeId);
        $actions = [];
        if ($manifest->state->isEditable() && $context->hasPermission('manifest.edit')) {
            $actions = ['EDIT', 'INSERT', 'VALIDATE'];
        }
        if ($manifest->state === ManifestState::Open && $context->hasPermission('manifest.approve')) {
            $actions[] = 'CONFIRM';
        }
        $exceptionState = in_array((string) $manifest->manifest_status, ['NPU', 'NOK'], true) ? $this->getManifestExceptionHandler->handle(new GetManifestExceptionCommand($actor, $nodeId, $id)) : null;

        return new ManifestDetailDto($base, $rows, $eligibilities, $actions,
            $this->statusEvents($manifest->hq_id, $id), $this->manifestConsignmentAccess->custodyEvents($manifest->hq_id, $id),
            $this->timeline($manifest->hq_id, $id), $exceptionState);
    }

    public function statusEvents(string $hqId, string $manifestId): Collection
    {
        return $this->manifestEvidenceRepository->statusHistory($hqId, $manifestId);
    }

    public function timeline(string $hqId, string $manifestId): Collection
    {
        return $this->manifestEvidenceRepository->auditTrail($hqId, $manifestId);
    }

    public function visible(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): ManifestRecord {
        $row = $this->manifestRepository->findAtNode($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }

    public function locked(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): ManifestRecord {
        $row = $this->manifestRepository->lockAtNode($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }

    public function counts(string $id): ManifestCountsDto
    {
        $raw = $this->manifestParcelRepository->statusTotals($id);

        return ManifestCountsDto::fromStatusCounts($raw);
    }
}
