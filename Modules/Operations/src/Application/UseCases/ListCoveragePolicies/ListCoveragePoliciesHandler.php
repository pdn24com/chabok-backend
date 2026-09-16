<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoveragePolicies;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListCoveragePoliciesHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
    )
    {
    }

    public function handle(ListCoveragePoliciesCommand $command): ListCoveragePoliciesResult
    {
        return new ListCoveragePoliciesResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        return $this->coverage->policies($hq, $filters);
    }
}
