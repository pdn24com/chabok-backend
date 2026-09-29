<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingZoneSet;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;
use Modules\Pricing\Application\Contracts\PricingInputInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingZoneWriterInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

final readonly class CreatePricingZoneSetHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private PricingInputInterface $pricingInput,
        private PricingZoneWriterInterface $pricingZoneWriter,
        private PricingChangeRecorderInterface $pricingChangeRecorder,
        private PricingReaderInterface $pricingReader,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    public function handle(CreatePricingZoneSetCommand $command): PricingZoneSetVersionRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');

        return $this->connection->transaction(function () use ($actor, $input, $correlationId): PricingZoneSetVersionRecord {
            $now = $this->clock->now();
            $setId = $this->pricingZoneRepository->createZoneSet([

                'hq_id' => $actor->hqId,
                'owner_key' => $actor->hqId,
                'code' => mb_strtoupper($input->code),
                'purpose' => $input->purpose->value,
                'title' => $input->title,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $versionId = $this->pricingZoneRepository->createZoneSetVersion([

                'pricing_zone_set_id' => $setId,
                'hq_id' => $actor->hqId,
                'version_number' => 1,
                'status' => VersionLifecycleStatus::Draft->value,
                'valid_from' => $this->pricingInput->databaseTimestamp($input->validFrom),
                'valid_to' => $this->pricingInput->databaseTimestamp($input->validTo),
                'lock_version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->pricingZoneWriter->replaceZones($versionId, $input->zones);
            $this->pricingChangeRecorder->record($actor, 'PRICING_ZONE_SET_CREATED', 'PRICING_ZONE_SET', $setId, $correlationId, ['version_id' => $versionId]);

            return $this->pricingReader->zoneVersion($actor, $versionId);
        }, attempts: 3);
    }
}
