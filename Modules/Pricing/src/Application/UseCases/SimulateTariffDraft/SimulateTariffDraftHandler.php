<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\SimulateTariffDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class SimulateTariffDraftHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingVersionGuard $pricingVersionGuard,
        private \Modules\Pricing\Application\Services\QuoteCalculator $quoteCalculator,
    )
    {
    }

    public function handle(SimulateTariffDraftCommand $command): SimulateTariffDraftResult
    {
        return new SimulateTariffDraftResult($this->execute($command->actor, $command->versionId, $command->input, $command->expectedVersion));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId, array $input, int $expectedVersion): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        return $this->transactions->run(function () use ($actor, $versionId, $input, $expectedVersion): array {
            $draft = $this->pricing->lockSimulationTariff($actor->hqId, $versionId);
            if ($draft === null) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            }
            $this->pricingVersionGuard->assertDraft($draft, $expectedVersion);
            return $this->quoteCalculator->calculate($actor, $input, '', $draft);
        });
    }
}
