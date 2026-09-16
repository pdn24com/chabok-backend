<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingZoneSet;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreatePricingZoneSetHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingInput $pricingInput,
        private \Modules\Pricing\Application\Services\PricingZoneWriter $pricingZoneWriter,
        private \Modules\Pricing\Application\Services\PricingChangeRecorder $pricingChangeRecorder,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
    )
    {
    }

    public function handle(CreatePricingZoneSetCommand $command): CreatePricingZoneSetResult
    {
        return new CreatePricingZoneSetResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $setId = $this->identifiers->uuid();
            $versionId = $this->identifiers->uuid();
            $now = $this->clock->now();
            $this->pricing->insertZoneSet([
                'pricing_zone_set_id' => $setId,
                'hq_id' => $actor->hqId,
                'owner_key' => $actor->hqId,
                'code' => mb_strtoupper($input['code']),
                'purpose' => $input['purpose'],
                'title' => $input['title'],
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->pricing->insertZoneVersion([
                'zone_set_version_id' => $versionId,
                'pricing_zone_set_id' => $setId,
                'hq_id' => $actor->hqId,
                'version_number' => 1,
                'status' => 'DRAFT',
                'valid_from' => $this->pricingInput->databaseTimestamp($input['valid_from'] ?? null),
                'valid_to' => $this->pricingInput->databaseTimestamp($input['valid_to'] ?? null),
                'lock_version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->pricingZoneWriter->replaceZones($versionId, (array) $input['zones']);
            $this->pricingChangeRecorder->record($actor, 'PRICING_ZONE_SET_CREATED', 'PRICING_ZONE_SET', $setId, $correlationId, ['version_id' => $versionId]);
            return $this->pricingReader->zoneVersion($actor, $versionId);
        });
    }
}
