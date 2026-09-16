<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifests;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListManifestsHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Manifest\Application\Repositories\ManifestRepository $manifests,
        private \Modules\Manifest\Application\ManifestOperationalContext $operationalContext,
    )
    {
    }

    public function handle(ListManifestsCommand $command): ListManifestsResult
    {
        return new ListManifestsResult($this->execute($command->actor, $command->nodeId, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $filters): Page
    {
        $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.view');
        $page = $this->manifests->list((string) $actor->hqId, $nodeId, $filters);
        $contexts = $this->operationalContext->summaries((string) $actor->hqId, $page->items());
        $counts = $this->manifests->batchCounts($actor->hqId, array_map(fn($manifest) => $manifest->manifest_id, $page->items()));
        foreach ($page->items() as $row) {
            $row->_list_context = $contexts[$row->manifest_id];
            $raw = array_column($counts[$row->manifest_id] ?? [], 'total', 'manifest_parcel_status');
            $row->_list_counts = [
                'pending' => (int) ($raw['PENDING'] ?? 0),
                'validated' => (int) ($raw['VALIDATED'] ?? 0),
                'succeeded' => (int) ($raw['SUCCEEDED'] ?? 0),
                'failed' => (int) ($raw['FAILED'] ?? 0),
                'skipped' => (int) ($raw['SKIPPED'] ?? 0),
            ];
        }
        return $page;
    }
}
