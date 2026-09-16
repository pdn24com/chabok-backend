<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\TransitionPricingVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class TransitionPricingVersionHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffHandler $validateTariff,
        private \Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetHandler $validatePricingZoneSet,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Pricing\Application\Services\PricingChangeRecorder $pricingChangeRecorder,
    )
    {
    }

    public function handle(TransitionPricingVersionCommand $command): TransitionPricingVersionResult
    {
        return new TransitionPricingVersionResult($this->execute($command->actor, $command->kind, $command->versionId, $command->action, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $kind,
        string $versionId,
        string $action,
        string $correlationId,
    ): array
    {
        $permission = $action === 'approve' ? 'pricing.tariff.approve' : 'pricing.tariff.publish';
        $this->pricingAccessGuard->assertAccess($actor, $permission);
        return $this->transactions->run(function () use ($actor, $kind, $versionId, $action, $correlationId): array {
            $this->pricing->lockTenant($actor->hqId);
            $row = $this->pricing->lockVersion($actor->hqId, $kind, $versionId);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ($action === 'approve') {
                if ((string) $row->status !== 'DRAFT') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft can be approved.');
                }
                if ($kind === 'tariffs') {
                    $validation = $this->validateTariff->handle(new \Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffCommand($actor, $versionId))->data;
                    if (!$validation['valid']) {
                        throw new ApiException(ApiErrorCode::ValidationError, 422, 'اعتبارسنجی تعرفه ناموفق بود؛ خطاها را پیش از تأیید اصلاح کنید.', details: $validation);
                    }
                }
                if ($kind === 'zone-sets' && !$this->validatePricingZoneSet->handle(new \Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetCommand($actor, $versionId))->data['valid']) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone Set validation failed.');
                }
                $changes = ['status' => 'APPROVED', 'approved_by' => $actor->userId, 'approved_at' => $this->clock->now()];
            } elseif ($action === 'publish') {
                if ((string) $row->status !== 'APPROVED') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.');
                }
                $validation = $kind === 'zone-sets' ? $this->validatePricingZoneSet->handle(new \Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetCommand($actor, $versionId))->data : $this->validateTariff->handle(new \Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffCommand($actor, $versionId))->data;
                if (!$validation['valid']) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Pricing validation failed.', details: $validation);
                }
                $detail = $kind === 'zone-sets' ? $this->pricingReader->zoneVersion($actor, $versionId) : $this->pricingReader->tariffVersion($actor, $versionId);
                $changes = [
                    'status' => 'PUBLISHED',
                    'published_by' => $actor->userId,
                    'published_at' => $this->clock->now(),
                    'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR)),
                ];
            } elseif ($action === 'supersede') {
                if ((string) $row->status !== 'PUBLISHED') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a published version can be superseded.');
                }
                $changes = ['status' => 'SUPERSEDED'];
            } elseif ($action === 'archive') {
                if ((string) $row->status !== 'SUPERSEDED') {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a superseded version can be archived.');
                }
                $changes = ['status' => 'ARCHIVED'];
            } else {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unsupported lifecycle action.');
            }
            $this->pricing->updateVersion($kind, $versionId, $changes + ['updated_at' => $this->clock->now()]);
            $this->pricingChangeRecorder->record($actor, 'PRICING_VERSION_' . mb_strtoupper($action) . 'D', 'PRICING_VERSION', $versionId, $correlationId, ['status' => $changes['status']]);
            return $kind === 'zone-sets' ? $this->pricingReader->zoneVersion($actor, $versionId) : $this->pricingReader->tariffVersion($actor, $versionId);
        });
    }
}
