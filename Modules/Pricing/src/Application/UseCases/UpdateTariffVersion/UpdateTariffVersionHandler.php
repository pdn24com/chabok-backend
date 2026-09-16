<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdateTariffVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateTariffVersionHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Pricing\Application\Services\PricingDraftPreparation $pricingDraftPreparation,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingVersionGuard $pricingVersionGuard,
        private \Modules\Pricing\Application\Services\PricingInput $pricingInput,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Services\PricingConfigurationWriter $pricingConfigurationWriter,
        private \Modules\Pricing\Application\ServiceTariffDependencies $serviceTariffs,
        private \Modules\Pricing\Application\Services\PricingChangeRecorder $pricingChangeRecorder,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function handle(UpdateTariffVersionCommand $command): UpdateTariffVersionResult
    {
        return new UpdateTariffVersionResult($this->execute($command->actor, $command->versionId, $command->input));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId, array $input): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $existing = $this->pricingReader->tariffVersion($actor, $versionId);
        $input['tariff_kind'] = $existing['tariff_kind'];
        $input['service_charge_type_id'] = $existing['service_charge_type_id'];
        $input = $this->pricingDraftPreparation->prepareTariffDraft($actor, $input);
        $this->pricingDraftPreparation->assertTariffReferences($actor, $input);
        return $this->transactions->run(function () use ($actor, $versionId, $input): array {
            $row = $this->pricing->lockTariffVersion($actor->hqId, $versionId);
            $this->pricingVersionGuard->assertDraft($row, (int) $input['expected_version']);
            $this->pricing->updateTariffVersion($versionId, [
                'zone_set_version_id' => $input['zone_set_version_id'],
                'zone_policy' => $input['zone_policy'],
                'matrix_basis' => $input['matrix_basis'] ?? 'BILLABLE_WEIGHT',
                'is_default' => $input['is_default'] ?? false,
                'freight_matrices' => json_encode($input['freight_matrices'], JSON_THROW_ON_ERROR),
                'valid_from' => $this->pricingInput->databaseTimestamp($input['valid_from'] ?? null),
                'valid_to' => $this->pricingInput->databaseTimestamp($input['valid_to'] ?? null),
                'volumetric_divisor' => $input['volumetric_divisor'] ?? 5000,
                'weight_rounding_step_kg' => $input['weight_rounding_step_kg'] ?? 0.5,
                'rounding_mode' => $input['rounding_mode'] ?? 'STEP_UP',
                'lock_version' => (int) $row->lock_version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->pricingConfigurationWriter->replaceRules($versionId, (array) $input['rules']);
            $this->serviceTariffs->replace($versionId, $input['service_tariff_family_ids'] ?? []);
            $this->pricingChangeRecorder->record($actor, 'PRICING_DRAFT_UPDATED', 'PRICING_VERSION', $versionId, $this->identifiers->uuid(), ['lock_version' => (int) $row->lock_version + 1]);
            return $this->pricingReader->tariffVersion($actor, $versionId);
        });
    }
}
