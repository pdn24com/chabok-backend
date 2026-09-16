<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdatePricingZoneVersionHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingVersionGuard $pricingVersionGuard,
        private \Modules\Pricing\Application\Services\PricingInput $pricingInput,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Services\PricingZoneWriter $pricingZoneWriter,
        private \Modules\Pricing\Application\Services\PricingChangeRecorder $pricingChangeRecorder,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
    )
    {
    }

    public function handle(UpdatePricingZoneVersionCommand $command): UpdatePricingZoneVersionResult
    {
        return new UpdatePricingZoneVersionResult($this->execute($command->actor, $command->versionId, $command->input));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId, array $input): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        return $this->transactions->run(function () use ($actor, $versionId, $input): array {
            $row = $this->pricing->lockZoneVersion($actor->hqId, $versionId);
            $this->pricingVersionGuard->assertDraft($row, (int) $input['expected_version']);
            $this->pricing->updateZoneVersion($versionId, [
                'valid_from' => $this->pricingInput->databaseTimestamp($input['valid_from'] ?? null),
                'valid_to' => $this->pricingInput->databaseTimestamp($input['valid_to'] ?? null),
                'lock_version' => (int) $row->lock_version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->pricingZoneWriter->replaceZones($versionId, (array) $input['zones']);
            $this->pricingChangeRecorder->record($actor, 'PRICING_ZONE_DRAFT_UPDATED', 'PRICING_VERSION', $versionId, $this->identifiers->uuid(), ['lock_version' => (int) $row->lock_version + 1]);
            return $this->pricingReader->zoneVersion($actor, $versionId);
        });
    }
}
