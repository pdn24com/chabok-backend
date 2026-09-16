<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifestCandidates;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListManifestCandidatesHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
        private \Modules\Manifest\Application\Repositories\ManifestRepository $manifests,
        private \Modules\Manifest\Application\ManifestEligibilityEvaluator $eligibility,
    )
    {
    }

    public function handle(ListManifestCandidatesCommand $command): ListManifestCandidatesResult
    {
        return new ListManifestCandidatesResult($this->execute($command->actor, $command->nodeId, $command->id, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $filters): Page
    {
        $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view');
        $manifest = $this->manifestReader->visible($actor, $nodeId, $id);
        $paginator = $this->manifests->candidates((string) $actor->hqId, $filters, $this->eligibility->candidateScope($manifest, $nodeId));
        $rows = array_map(fn(object $row): array => [
            'parcel_id' => (string) $row->parcel_id,
            'parcel_number' => (string) $row->parcel_number,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => (string) $row->consignment_number,
            'receiver_contact_name' => (string) $row->receiver_contact_name,
            'current_status' => (string) $row->current_status,
            'eligibility' => $this->eligibility->evaluate($row, $manifest, $nodeId),
        ], $paginator->items());
        return new Page($rows, $paginator->page, $paginator->pageSize, $paginator->totalRows);
    }
}
