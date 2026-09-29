<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\SimulateTariffDraft;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingVersionGuardInterface;
use Modules\Pricing\Application\Contracts\QuoteCalculatorInterface;
use Modules\Pricing\Application\Dto\PricingSimulationDto;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;

final readonly class SimulateTariffDraftHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ConnectionInterface $connection,
        private PricingVersionGuardInterface $pricingVersionGuard,
        private QuoteCalculatorInterface $quoteCalculator,
        private TariffRepositoryInterface $tariffRepository,
    ) {}

    public function handle(SimulateTariffDraftCommand $command): PricingSimulationDto
    {
        $actor = $command->actor;
        $versionId = $command->versionId;
        $input = $command->input;
        $expectedVersion = $command->expectedVersion;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');

        return $this->connection->transaction(function () use ($actor, $versionId, $input, $expectedVersion): PricingSimulationDto {
            $draft = $this->tariffRepository->lockTenantDraftWithFamily($actor->hqId, $versionId);
            if ($draft === null) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
            }
            $this->pricingVersionGuard->assertDraft($draft, $expectedVersion);

            return $this->quoteCalculator->calculate($actor, $input, '', $draft);
        }, attempts: 3);
    }
}
