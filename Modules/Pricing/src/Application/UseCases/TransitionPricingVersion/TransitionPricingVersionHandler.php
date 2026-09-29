<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\TransitionPricingVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Organization\Application\Repositories\TenantRepositoryInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Repositories\PricingVersionRepositoryInterface;
use Modules\Pricing\Application\Serialization\PricingVersionSnapshot;
use Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetCommand;
use Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetHandler;
use Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffCommand;
use Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffHandler;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Enums\PricingTransition;
use Modules\Pricing\Domain\Exceptions\PricingValidationFailed;
use Modules\Pricing\Domain\Policies\PricingVersionPolicy;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class TransitionPricingVersionHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ConnectionInterface $connection,
        private ValidateTariffHandler $validateTariffHandler,
        private ValidatePricingZoneSetHandler $validatePricingZoneSetHandler,
        private ClockInterface $clock,
        private PricingReaderInterface $pricingReader,
        private PricingChangeRecorderInterface $pricingChangeRecorder,
        private PricingVersionRepositoryInterface $pricingVersionRepository,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    public function handle(TransitionPricingVersionCommand $command): TariffVersionRecord|PricingZoneSetVersionRecord
    {
        $actor = $command->actor;
        $kind = $command->kind;
        $versionId = $command->versionId;
        $action = $command->action;
        $correlationId = $command->correlationId;
        $permission = $action === PricingTransition::Approve ? 'pricing.tariff.approve' : 'pricing.tariff.publish';
        $this->pricingAccessGuard->assertAccess($actor, $permission);

        return $this->connection->transaction(function () use ($actor, $kind, $versionId, $action, $correlationId): TariffVersionRecord|PricingZoneSetVersionRecord {
            $this->tenantRepository->lockIdentity((string) $actor->hqId);
            $row = $this->pricingVersionRepository->lockTenantVersion($kind, $versionId, $actor->hqId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            PricingVersionPolicy::assertTransition($row->status, $action);
            if ($action === PricingTransition::Approve) {
                if ($kind === PricingResource::Tariffs) {
                    $validation = $this->validateTariffHandler->handle(new ValidateTariffCommand($actor, $versionId));
                    if (! $validation->valid) {
                        throw new PricingValidationFailed($validation, 'pricing.tariff_validation_failed_fix_errors');
                    }
                }
                if ($kind === PricingResource::ZoneSets && ! $this->validatePricingZoneSetHandler->handle(new ValidatePricingZoneSetCommand($actor, $versionId))->valid) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.zone_set_validation_failed');
                }
                $changes = [
                    'status' => VersionLifecycleStatus::Approved->value,
                    'approved_by' => $actor->userId,
                    'approved_at' => $this->clock->now(),
                ];
            } elseif ($action === PricingTransition::Publish) {
                $validation = $kind === PricingResource::ZoneSets ? $this->validatePricingZoneSetHandler->handle(new ValidatePricingZoneSetCommand($actor, $versionId)) : $this->validateTariffHandler->handle(new ValidateTariffCommand($actor, $versionId));
                if (! $validation->valid) {
                    throw new PricingValidationFailed($validation, 'pricing.pricing_validation_failed');
                }
                $detail = $kind === PricingResource::ZoneSets ? $this->pricingReader->zoneVersion($actor, $versionId) : $this->pricingReader->tariffVersion($actor, $versionId);
                $changes = [
                    'status' => VersionLifecycleStatus::Published->value,
                    'published_by' => $actor->userId,
                    'published_at' => $this->clock->now(),
                    'content_digest' => hash('sha256', json_encode(PricingVersionSnapshot::serialize($detail), JSON_THROW_ON_ERROR)),
                ];
            } elseif ($action === PricingTransition::Supersede) {
                $changes = ['status' => VersionLifecycleStatus::Superseded->value];
            } elseif ($action === PricingTransition::Archive) {
                $changes = ['status' => VersionLifecycleStatus::Archived->value];
            }
            $row->forceFill($changes + ['updated_at' => $this->clock->now()])->save();
            $this->pricingChangeRecorder->record($actor, 'PRICING_VERSION_'.mb_strtoupper($action->value).'D', 'PRICING_VERSION', $versionId, $correlationId, ['status' => $changes['status']]);

            return $kind === PricingResource::ZoneSets ? $this->pricingReader->zoneVersion($actor, $versionId) : $this->pricingReader->tariffVersion($actor, $versionId);
        }, attempts: 3);
    }
}
