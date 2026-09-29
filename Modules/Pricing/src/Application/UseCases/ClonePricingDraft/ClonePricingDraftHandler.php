<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ClonePricingDraft;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;
use Modules\Pricing\Application\Contracts\PricingConfigurationWriterInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingZoneWriterInterface;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Dto\PricingRateRuleDraftDto;
use Modules\Pricing\Application\Mappers\PricingZoneInput;
use Modules\Pricing\Application\Repositories\PricingVersionRepositoryInterface;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class ClonePricingDraftHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private PricingReaderInterface $pricingReader,
        private PricingConfigurationWriterInterface $pricingConfigurationWriter,
        private ServiceTariffDependenciesInterface $serviceTariffDependencies,
        private PricingZoneWriterInterface $pricingZoneWriter,
        private PricingChangeRecorderInterface $pricingChangeRecorder,
        private PricingVersionRepositoryInterface $pricingVersionRepository,
    ) {}

    public function handle(ClonePricingDraftCommand $command): TariffVersionRecord|PricingZoneSetVersionRecord
    {
        $actor = $command->actor;
        $kind = $command->kind;
        $identityId = $command->identityId;
        $correlationId = $command->correlationId;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $versionId = $kind->versionKey();

        return $this->connection->transaction(function () use ($actor, $kind, $identityId, $correlationId, $versionId): TariffVersionRecord|PricingZoneSetVersionRecord {
            $resource = $kind;
            // Serialize concurrent clones on the stable identity, including an empty version set.
            $identity = $this->pricingVersionRepository->lockTenantIdentity($resource, $identityId, $actor->hqId);
            if ($identity === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $previous = $this->pricingVersionRepository->lockLatestVersionOf($resource, $identityId);
            if ($previous === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($this->pricingVersionRepository->hasVersionWithStatus($resource, $identityId, VersionLifecycleStatus::valuesOf(VersionLifecycleStatus::unpublished()))) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'pricing.unpublished_successor_already_exists');
            }
            $previousId = (string) $previous->{$versionId};
            $newId = $this->pricingVersionRepository->replicateAsDraft($resource, $previous, [
                'previous_version_id' => $previousId,
                'version_number' => $previous->version_number + 1, 'status' => VersionLifecycleStatus::Draft->value, 'lock_version' => 1,
                'valid_from' => null, 'valid_to' => null, 'created_by' => $actor->userId,
                'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ]);
            if ($kind === PricingResource::Tariffs) {
                $rules = $previous->rules->map(static fn ($rule): PricingRateRuleDraftDto => PricingRateRuleDraftDto::fromInput($rule->attributesToArray()))->all();
                $this->pricingConfigurationWriter->replaceRules($newId, $rules);
                $this->serviceTariffDependencies->replace($newId, $this->serviceTariffDependencies->ids($previousId));
            } else {
                $this->pricingZoneWriter->replaceZones($newId, PricingZoneInput::fromRecords($this->pricingReader->zoneVersion($actor, $previousId)->zones), $previousId);
            }
            $this->pricingChangeRecorder->record($actor, 'PRICING_DRAFT_CLONED', 'PRICING_VERSION', $newId, $correlationId, ['previous_version_id' => $previousId]);

            return $kind === PricingResource::Tariffs ? $this->pricingReader->tariffVersion($actor, $newId) : $this->pricingReader->zoneVersion($actor, $newId);
        }, attempts: 3);
    }
}
