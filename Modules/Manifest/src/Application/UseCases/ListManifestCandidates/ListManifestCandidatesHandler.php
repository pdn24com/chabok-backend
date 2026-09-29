<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifestCandidates;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestEligibilityEvaluatorInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Repositories\ManifestCandidateRepositoryInterface;
use Modules\Manifest\Application\Serialization\ManifestEligibilityDocument;

final readonly class ListManifestCandidatesHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestReaderInterface $manifestReader,
        private ManifestEligibilityEvaluatorInterface $manifestEligibilityEvaluator,
        private ManifestCandidateRepositoryInterface $manifestCandidateRepository,
    ) {}

    public function handle(ListManifestCandidatesCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $filters = $command->filters;
        $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view');
        $manifest = $this->manifestReader->visible($actor, $nodeId, $id);
        $paginator = $this->manifestCandidateRepository->paginateCandidates((string) $actor->hqId, $filters, $this->manifestEligibilityEvaluator->candidateScope($manifest, $nodeId));
        $eligibility = $this->manifestEligibilityEvaluator->evaluateMany($paginator->getCollection(), $manifest, $nodeId);
        $rows = array_map(fn (ParcelRecord $row): array => [
            'parcel_id' => (string) $row->parcel_id,
            'parcel_number' => (string) $row->parcel_number,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => (string) $row->consignment->consignment_number,
            'receiver_contact_name' => (string) $row->consignment->receiver_contact_name,
            'current_status' => (string) $row->current_status,
            'eligibility' => ManifestEligibilityDocument::metadata($eligibility[$row->parcel_id]),
        ], $paginator->items());

        return $paginator->setCollection(collect($rows));
    }
}
