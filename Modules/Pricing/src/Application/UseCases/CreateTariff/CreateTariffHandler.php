<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreateTariff;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateTariffHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Services\PricingDraftPreparation $pricingDraftPreparation,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingInput $pricingInput,
        private \Modules\Pricing\Application\Services\PricingConfigurationWriter $pricingConfigurationWriter,
        private \Modules\Pricing\Application\ServiceTariffDependencies $serviceTariffs,
        private \Modules\Pricing\Application\Services\PricingChangeRecorder $pricingChangeRecorder,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
    )
    {
    }

    public function handle(CreateTariffCommand $command): CreateTariffResult
    {
        return new CreateTariffResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        if ($input['currency'] !== 'IRR') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Milestone 1 supports IRR only.');
        }
        if (!in_array((string) ($input['scope_type'] ?? 'TENANT'), ['TENANT', 'PLATFORM'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected tariff scope has no authoritative reference directory.');
        }
        $input = $this->pricingDraftPreparation->prepareTariffDraft($actor, $input);
        $this->pricingDraftPreparation->assertTariffReferences($actor, $input);
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $familyId = $this->identifiers->uuid();
            $versionId = $this->identifiers->uuid();
            $now = $this->clock->now();
            $scopeType = (string) ($input['scope_type'] ?? 'TENANT');
            $scopeValue = match ($scopeType) {
                'TENANT' => $actor->hqId,
                'PLATFORM' => null,
                default => $input['scope_value'] ?? null,
            };
            $automaticCode = ($input['code'] ?? '') === '';
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $code = $automaticCode ? (string) random_int(100000000, 999999999) : mb_strtoupper($input['code']);
                try {
                    $this->pricing->insertTariffFamily([
                        'tariff_family_id' => $familyId,
                        'hq_id' => $actor->hqId,
                        'owner_key' => $actor->hqId,
                        'code' => $code,
                        'tariff_kind' => $input['tariff_kind'] ?? 'FREIGHT',
                        'service_charge_type_id' => $input['service_charge_type_id'] ?? null,
                        'title' => isset($input['title']) ? trim((string) $input['title']) : null,
                        'purpose' => $input['purpose'],
                        'scope_type' => $scopeType,
                        'scope_value' => $scopeValue,
                        'currency' => 'IRR',
                        'priority' => $input['priority'] ?? 100,
                        'created_by' => $actor->userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    break;
                } catch (\Modules\Pricing\Domain\TariffCodeConflict $exception) {
                    if (!$automaticCode || $attempt === 9) {
                        throw new ApiException(ApiErrorCode::Conflict, 409, 'کد تعرفه تکراری است؛ کد دیگری انتخاب کنید.');
                    }
                }
            }
            $this->pricing->insertTariffVersion([
                'tariff_version_id' => $versionId,
                'tariff_family_id' => $familyId,
                'hq_id' => $actor->hqId,
                'zone_set_version_id' => $input['zone_set_version_id'],
                'zone_policy' => $input['zone_policy'],
                'matrix_basis' => $input['matrix_basis'] ?? 'BILLABLE_WEIGHT',
                'is_default' => $input['is_default'] ?? false,
                'freight_matrices' => json_encode($input['freight_matrices'], JSON_THROW_ON_ERROR),
                'version_number' => 1,
                'status' => 'DRAFT',
                'valid_from' => $this->pricingInput->databaseTimestamp($input['valid_from'] ?? null),
                'valid_to' => $this->pricingInput->databaseTimestamp($input['valid_to'] ?? null),
                'lock_version' => 1,
                'volumetric_divisor' => $input['volumetric_divisor'] ?? 5000,
                'weight_rounding_step_kg' => $input['weight_rounding_step_kg'] ?? 0.5,
                'rounding_mode' => $input['rounding_mode'] ?? 'STEP_UP',
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->pricingConfigurationWriter->replaceRules($versionId, (array) $input['rules']);
            $this->serviceTariffs->replace($versionId, $input['service_tariff_family_ids'] ?? []);
            $this->pricingChangeRecorder->record($actor, 'TARIFF_FAMILY_CREATED', 'TARIFF_FAMILY', $familyId, $correlationId, ['version_id' => $versionId]);
            return $this->pricingReader->tariffVersion($actor, $versionId);
        });
    }
}
