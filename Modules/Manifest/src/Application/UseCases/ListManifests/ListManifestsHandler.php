<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifests;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestContextProjectionInterface;
use Modules\Manifest\Application\Dto\ManifestCountsDto;
use Modules\Manifest\Application\Dto\ManifestListItemDto;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class ListManifestsHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestContextProjectionInterface $manifestContextProjection,
        private ManifestRepositoryInterface $manifestRepository,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
    ) {}

    public function handle(ListManifestsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $filters = $command->filters;
        $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view');
        $page = $this->manifestRepository->paginateAtNode($actor->hqId, $nodeId, $filters);
        $contexts = $this->manifestContextProjection->summaries((string) $actor->hqId, $page->items());
        $counts = $this->manifestParcelRepository->statusTotalsByManifest($actor->hqId, array_map(fn ($manifest) => $manifest->manifest_id, $page->items()));
        $page->setCollection($page->getCollection()->map(function (ManifestRecord $row) use ($contexts, $counts): ManifestListItemDto {
            $raw = $counts->get($row->manifest_id, collect())->pluck('total', 'manifest_parcel_status')->all();

            return new ManifestListItemDto($row, ManifestCountsDto::fromStatusCounts($raw), $contexts[$row->manifest_id]);
        }));

        return $page;
    }
}
