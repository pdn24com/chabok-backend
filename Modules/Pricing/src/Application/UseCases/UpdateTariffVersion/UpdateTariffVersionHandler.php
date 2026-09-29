<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdateTariffVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\IdentifierGeneratorInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;
use Modules\Pricing\Application\Contracts\PricingConfigurationWriterInterface;
use Modules\Pricing\Application\Contracts\PricingDraftPreparationInterface;
use Modules\Pricing\Application\Contracts\PricingInputInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingVersionGuardInterface;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Application\Serialization\FreightMatrixDraftDocument;
use Modules\Pricing\Domain\Enums\TariffKind;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class UpdateTariffVersionHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingReaderInterface $pricingReader,
        private PricingDraftPreparationInterface $pricingDraftPreparation,
        private ConnectionInterface $connection,
        private PricingVersionGuardInterface $pricingVersionGuard,
        private PricingInputInterface $pricingInput,
        private ClockInterface $clock,
        private PricingConfigurationWriterInterface $pricingConfigurationWriter,
        private ServiceTariffDependenciesInterface $serviceTariffDependencies,
        private PricingChangeRecorderInterface $pricingChangeRecorder,
        private IdentifierGeneratorInterface $identifierGenerator,
        private TariffRepositoryInterface $tariffRepository,
    ) {}

    public function handle(UpdateTariffVersionCommand $command): TariffVersionRecord
    {
        $actor = $command->actor;
        $versionId = $command->versionId;
        $input = $command->input;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $existing = $this->pricingReader->tariffVersion($actor, $versionId);
        $input->kind = TariffKind::from($existing->family->tariff_kind);
        $input->serviceChargeTypeId = $existing->family->service_charge_type_id;
        $input = $this->pricingDraftPreparation->prepareTariffDraft($actor, $input);
        $this->pricingDraftPreparation->assertTariffReferences($actor, $input);

        return $this->connection->transaction(function () use ($actor, $versionId, $input): TariffVersionRecord {
            $row = $this->tariffRepository->lockTenantVersion($actor->hqId, $versionId);
            $this->pricingVersionGuard->assertDraft($row, (int) $input->expectedVersion);
            $row->forceFill([
                'zone_set_version_id' => $input->zoneSetVersionId,
                'zone_policy' => $input->zonePolicy->value,
                'matrix_basis' => $input->matrixBasis->value,
                'is_default' => $input->isDefault,
                'freight_matrices' => FreightMatrixDraftDocument::many($input->freightMatrices),
                'valid_from' => $this->pricingInput->databaseTimestamp($input->validFrom),
                'valid_to' => $this->pricingInput->databaseTimestamp($input->validTo),
                'volumetric_divisor' => $input->volumetricDivisor,
                'weight_rounding_step_kg' => $input->weightRoundingStepKg,
                'rounding_mode' => $input->roundingMode->value,
                'lock_version' => (int) $row->lock_version + 1,
                'updated_at' => $this->clock->now(),
            ])->save();
            $this->pricingConfigurationWriter->replaceRules($versionId, $input->rules);
            $this->serviceTariffDependencies->replace($versionId, $input->serviceTariffFamilyIds);
            $this->pricingChangeRecorder->record($actor, 'PRICING_DRAFT_UPDATED', 'PRICING_VERSION', $versionId, $this->identifierGenerator->token(), ['lock_version' => (int) $row->lock_version]);

            return $this->pricingReader->tariffVersion($actor, $versionId);
        }, attempts: 3);
    }
}
