<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ClonePricingDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ClonePricingDraftHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Services\PricingVersionGuard $pricingVersionGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Pricing\Application\Services\PricingConfigurationWriter $pricingConfigurationWriter,
        private \Modules\Pricing\Application\ServiceTariffDependencies $serviceTariffs,
        private \Modules\Pricing\Application\Services\PricingZoneWriter $pricingZoneWriter,
        private \Modules\Pricing\Application\Services\PricingChangeRecorder $pricingChangeRecorder,
    )
    {
    }

    public function handle(ClonePricingDraftCommand $command): ClonePricingDraftResult
    {
        return new ClonePricingDraftResult($this->execute($command->actor, $command->kind, $command->identityId, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $kind, string $identityId, string $correlationId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        [$parentId, $versionId] = $this->pricingVersionGuard->pricingMap($kind);
        return $this->transactions->run(function () use ($actor, $kind, $identityId, $correlationId, $parentId, $versionId): array {
            if (!$this->pricing->tenantIdentityExists($actor->hqId, $kind, $identityId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $previous = (array) $this->pricing->lockLatestVersion($kind, $identityId);
            if ($previous === []) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ($this->pricing->hasUnpublishedSuccessor($kind, $identityId)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'An unpublished successor already exists.');
            }
            $previousId = (string) $previous[$versionId];
            $newId = $this->identifiers->uuid();
            unset($previous[$versionId], $previous['approved_by'], $previous['published_by'], $previous['approved_at'], $previous['published_at'], $previous['content_digest']);
            $previous[$versionId] = $newId;
            $previous['previous_version_id'] = $previousId;
            $previous['version_number'] = (int) $previous['version_number'] + 1;
            $previous['status'] = 'DRAFT';
            $previous['lock_version'] = 1;
            $previous['valid_from'] = null;
            $previous['valid_to'] = null;
            $previous['created_by'] = $actor->userId;
            $previous['created_at'] = $this->clock->now();
            $previous['updated_at'] = $this->clock->now();
            $this->pricing->insertVersion($kind, $previous);
            if ($kind === 'tariffs') {
                $rules = array_map(fn($row) => $this->pricingReader->decode((array) $row), $this->pricing->unorderedRules($previousId));
                $this->pricingConfigurationWriter->replaceRules($newId, $rules);
                $this->serviceTariffs->replace($newId, $this->serviceTariffs->ids($previousId));
            } else {
                $this->pricingZoneWriter->replaceZones($newId, $this->pricingReader->zoneVersion($actor, $previousId)['zones'], $previousId);
            }
            $this->pricingChangeRecorder->record($actor, 'PRICING_DRAFT_CLONED', 'PRICING_VERSION', $newId, $correlationId, ['previous_version_id' => $previousId]);
            return $kind === 'tariffs' ? $this->pricingReader->tariffVersion($actor, $newId) : $this->pricingReader->zoneVersion($actor, $newId);
        });
    }
}
