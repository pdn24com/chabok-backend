<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Contracts\IdentifierGeneratorInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;
use Modules\Pricing\Application\Contracts\PricingInputInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingVersionGuardInterface;
use Modules\Pricing\Application\Contracts\PricingZoneWriterInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

final readonly class UpdatePricingZoneVersionHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ConnectionInterface $connection,
        private PricingVersionGuardInterface $pricingVersionGuard,
        private PricingInputInterface $pricingInput,
        private ClockInterface $clock,
        private PricingZoneWriterInterface $pricingZoneWriter,
        private PricingChangeRecorderInterface $pricingChangeRecorder,
        private IdentifierGeneratorInterface $identifierGenerator,
        private PricingReaderInterface $pricingReader,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    public function handle(UpdatePricingZoneVersionCommand $command): PricingZoneSetVersionRecord
    {
        $actor = $command->actor;
        $versionId = $command->versionId;
        $input = $command->input;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');

        return $this->connection->transaction(function () use ($actor, $versionId, $input): PricingZoneSetVersionRecord {
            $row = $this->pricingZoneRepository->lockTenantVersion($actor->hqId, $versionId);
            $this->pricingVersionGuard->assertDraft($row, (int) $input->expectedVersion);
            $row->forceFill([
                'valid_from' => $this->pricingInput->databaseTimestamp($input->validFrom),
                'valid_to' => $this->pricingInput->databaseTimestamp($input->validTo),
                'lock_version' => (int) $row->lock_version + 1,
                'updated_at' => $this->clock->now(),
            ])->save();
            $this->pricingZoneWriter->replaceZones($versionId, $input->zones);
            $this->pricingChangeRecorder->record($actor, 'PRICING_ZONE_DRAFT_UPDATED', 'PRICING_VERSION', $versionId, $this->identifierGenerator->token(), ['lock_version' => (int) $row->lock_version]);

            return $this->pricingReader->zoneVersion($actor, $versionId);
        }, attempts: 3);
    }
}
