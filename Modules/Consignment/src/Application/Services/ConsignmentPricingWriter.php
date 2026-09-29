<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Consignment\Application\Contracts\ConsignmentPricingWriterInterface;
use Modules\Consignment\Application\Dto\AcceptedConsignmentQuoteDto;
use Modules\Consignment\Application\Repositories\ConsignmentPricingRepositoryInterface;
use Modules\Consignment\Application\Serialization\ConsignmentQuoteDocument;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentPricingChargeLineRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentPricingVersionRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Pricing\Application\Contracts\ConsignmentQuoteAcceptanceInterface;

final readonly class ConsignmentPricingWriter implements ConsignmentPricingWriterInterface
{
    public function __construct(
        private ClockInterface $clock,
        private ConsignmentQuoteAcceptanceInterface $consignmentQuoteAcceptance,
        private ConsignmentPricingRepositoryInterface $consignmentPricingRepository,
    ) {}

    public function persistPricing(
        ConsignmentRecord $consignment,
        int $version,
        string $actorId,
        AcceptedConsignmentQuoteDto $accepted,
    ): string {
        $hqId = $consignment->hq_id;
        $consignmentId = $consignment->consignment_id;
        $acceptedAt = $this->clock->now();
        $snapshotId = ($accepted->option->providerCode ?? 'LEGACY_CORE') === 'INTERNAL' ? $this->consignmentQuoteAcceptance->acceptForConsignment($hqId, $consignmentId, $actorId, $version, $accepted->option->internalQuoteId) : null;
        $pricing = new ConsignmentPricingVersionRecord;
        $pricing->forceFill([

            'pricing_snapshot_id' => $snapshotId,
            'service_offering_version_id' => $accepted->option->serviceOfferingVersionId ?? null,
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'version_number' => $version,
            'provider_code' => $accepted->option->providerCode ?? 'LEGACY_CORE',
            'quote_id' => $accepted->quoteId,
            'quote_version' => $accepted->quoteVersion,
            'option_id' => $accepted->option->optionId,
            'external_method_code' => $accepted->option->externalMethodCode,
            'method_name' => $accepted->option->methodName,
            'external_price_list_code' => $accepted->option->externalPriceListCode,
            'zone' => $accepted->option->zone,
            'currency' => $accepted->option->currency,
            'total_amount' => $accepted->option->totalAmount,
            'min_ins' => $accepted->option->minIns,
            'delivery_windows' => array_map(ConsignmentQuoteDocument::window(...), $accepted->option->deliveryWindows),
            'input_fingerprint' => $accepted->inputFingerprint,
            'result_fingerprint' => $accepted->option->resultFingerprint ?? null,
            'provider_calculated_at' => CarbonImmutable::parse($accepted->providerCalculatedAt),
            'accepted_at' => $acceptedAt,
            'accepted_by' => $actorId,
        ]);
        $pricing->save();
        $id = (string) $pricing->getKey();
        $lines = [];
        foreach ($accepted->option->chargeLines as $index => $line) {
            $record = new ConsignmentPricingChargeLineRecord;
            $record->forceFill([

                'hq_id' => $hqId,
                'pricing_version_id' => $id,
                'line_number' => $index + 1,
                'charge_code' => $line->chargeCode,
                'title' => $line->title,
                'category' => $line->category ?? null,
                'calculation_method' => $line->calculationMethod ?? null,
                'basis' => $line->basis ?? null,
                'quantity' => $line->quantity ?? null,
                'unit_rate' => $line->unitRate ?? null,
                'amount' => $line->amount,
                'explanation' => $line->explanation ?? null,
            ]);
            $lines[] = $record->getAttributes();
        }
        foreach (array_chunk($lines, 100) as $batch) {
            $this->consignmentPricingRepository->insertChargeLines($batch);
        }
        $consignment->forceFill([
            'catalog_snapshot' => $accepted->option->catalogSnapshot ?? null,
            'commercial_pricing_state' => 'LOCKED',
            'active_pricing_snapshot_id' => $snapshotId,
            'pricing_relevant_fingerprint' => $accepted->inputFingerprint,
        ]);

        if ($accepted->option->serviceOfferingId !== null) {
            $consignment->service_offering_id = $accepted->option->serviceOfferingId;
        }
        if ($accepted->option->serviceOfferingVersionId !== null) {
            $consignment->service_offering_version_id = $accepted->option->serviceOfferingVersionId;
        }
        $consignment->save();

        return $id;
    }
}
