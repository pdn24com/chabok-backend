<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreateTariff;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\Currency;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;
use Modules\Pricing\Application\Contracts\PricingConfigurationWriterInterface;
use Modules\Pricing\Application\Contracts\PricingDraftPreparationInterface;
use Modules\Pricing\Application\Contracts\PricingInputInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Dto\TariffDraftDto;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Application\Serialization\FreightMatrixDraftDocument;
use Modules\Pricing\Domain\Enums\TariffScope;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class CreateTariffHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingDraftPreparationInterface $pricingDraftPreparation,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private PricingInputInterface $pricingInput,
        private PricingConfigurationWriterInterface $pricingConfigurationWriter,
        private ServiceTariffDependenciesInterface $serviceTariffDependencies,
        private PricingChangeRecorderInterface $pricingChangeRecorder,
        private PricingReaderInterface $pricingReader,
        private TariffRepositoryInterface $tariffRepository,
    ) {}

    public function handle(CreateTariffCommand $command): TariffVersionRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        if ($input->currency !== Currency::Irr->value) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.milestone_1_supports_irr_only');
        }
        if (! in_array($input->scopeType, [TariffScope::Tenant, TariffScope::Platform], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.selected_tariff_scope_has_no_authoritative_reference');
        }
        $input = $this->pricingDraftPreparation->prepareTariffDraft($actor, $input);
        $this->pricingDraftPreparation->assertTariffReferences($actor, $input);

        return $this->connection->transaction(function () use ($actor, $input, $correlationId): TariffVersionRecord {
            $now = $this->clock->now();
            $scopeType = $input->scopeType;
            $scopeValue = match ($scopeType) {
                TariffScope::Tenant => $actor->hqId,
                TariffScope::Platform => null,
                default => $input->scopeValue,
            };
            $familyId = $this->createFamily($actor, $input, $scopeValue, $now);
            $versionId = $this->tariffRepository->createVersion([
                'tariff_family_id' => $familyId,
                'hq_id' => $actor->hqId,
                'zone_set_version_id' => $input->zoneSetVersionId,
                'zone_policy' => $input->zonePolicy->value,
                'matrix_basis' => $input->matrixBasis->value,
                'is_default' => $input->isDefault,
                'freight_matrices' => FreightMatrixDraftDocument::many($input->freightMatrices),
                'version_number' => 1,
                'status' => VersionLifecycleStatus::Draft->value,
                'valid_from' => $this->pricingInput->databaseTimestamp($input->validFrom),
                'valid_to' => $this->pricingInput->databaseTimestamp($input->validTo),
                'lock_version' => 1,
                'volumetric_divisor' => $input->volumetricDivisor,
                'weight_rounding_step_kg' => $input->weightRoundingStepKg,
                'rounding_mode' => $input->roundingMode->value,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->pricingConfigurationWriter->replaceRules($versionId, $input->rules);
            $this->serviceTariffDependencies->replace($versionId, $input->serviceTariffFamilyIds);
            $this->pricingChangeRecorder->record($actor, 'TARIFF_FAMILY_CREATED', 'TARIFF_FAMILY', $familyId, $correlationId, ['version_id' => $versionId]);

            return $this->pricingReader->tariffVersion($actor, $versionId);
        }, attempts: 3);
    }

    private function createFamily(AuthenticatedPrincipal $actor, TariffDraftDto $input, ?string $scopeValue, DateTimeImmutable $now): string
    {
        $automaticCode = ($input->code) === '';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $automaticCode ? (string) random_int(100000000, 999999999) : mb_strtoupper($input->code);
            try {
                return $this->tariffRepository->createFamily([
                    'hq_id' => $actor->hqId,
                    'owner_key' => $actor->hqId,
                    'code' => $code,
                    'tariff_kind' => $input->kind->value,
                    'service_charge_type_id' => $input->serviceChargeTypeId,
                    'title' => $input->title,
                    'purpose' => $input->purpose->value,
                    'scope_type' => $input->scopeType->value,
                    'scope_value' => $scopeValue,
                    'currency' => Currency::Irr->value,
                    'priority' => $input->priority,
                    'created_by' => $actor->userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $automaticCode || $attempt === 9) {
                    throw new ApiException(ApiErrorCode::Conflict, 409, 'pricing.tariff_code_is_duplicate');
                }
            }
        }

        throw new ApiException(ApiErrorCode::Conflict, 409, 'pricing.tariff_code_is_duplicate');
    }
}
