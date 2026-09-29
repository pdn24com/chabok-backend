<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Application\Dto\QuoteResolutionDto;
use Modules\Pricing\Application\Dto\ServiceTariffEvidenceDto;
use Modules\ServiceCatalog\Application\Serialization\OfferingCommitmentDocument;
use Modules\ServiceCatalog\Application\Serialization\SelectedServiceOptionDocument;

final class QuoteEvidenceDocument
{
    public static function serialize(QuoteResolutionDto $resolution): array
    {
        $tariff = $resolution->tariff;
        $offering = $resolution->offering;
        $lane = $resolution->lane;
        $basisZone = $resolution->basisZone;
        $origin = $lane->origin->zone;
        $originEvidence = ZoneDocument::match($lane->origin);
        $destinationEvidence = ZoneDocument::match($lane->destination);
        $facts = $resolution->facts;
        $resolvedZoneSetVersionId = $resolution->resolvedZoneSetVersionId;
        $currentOptionVersions = $resolution->currentOptionVersions;
        $selectedOptionIdentities = $resolution->selectedOptionIdentities;
        $matrixCell = $resolution->matrixCell;
        $evidence = [
            'tariff_code' => $tariff->family->code,
            'zone_set' => [
                'configured_version_id' => (string) $tariff->zone_set_version_id,
                'resolved_version_id' => $resolvedZoneSetVersionId,
            ],
            'origin' => $originEvidence,
            'destination' => $destinationEvidence,
            'weight' => PricingCalculationSerializer::facts($facts),
            'service' => [
                'outcome' => $offering->outcome,
                'reason_codes' => $offering->reasonCodes,
                'labels' => $offering->labels ?? [],
                'service_type_labels' => $offering->serviceTypeLabels ?? [],
                'shipping_method_labels' => $offering->shippingMethodLabels ?? [],
                'service_type_id' => $offering->serviceTypeId,
                'shipping_method_id' => $offering->shippingMethodId,
                'selected_option_version_ids' => $currentOptionVersions,
                'selected_services' => array_map(SelectedServiceOptionDocument::serialize(...), array_values(array_filter($offering->options, fn ($option) => isset($selectedOptionIdentities[$option->optionId])))),
                'service_offering_version_id' => $offering->offeringVersionId,
                'service_type_version_id' => $offering->serviceTypeVersionId,
                'shipping_method_version_id' => $offering->shippingMethodVersionId,
                'commitment' => $offering->commitment === null ? null : OfferingCommitmentDocument::serialize($offering->commitment),
            ],
        ];
        $evidence['tariff_title'] = $tariff->family->title;
        $evidence['tariff_version_number'] = (int) $tariff->version_number;
        $evidence['zone_policy'] = $tariff->zone_policy;
        $evidence['zones'] = [
            'origin' => ZoneDocument::zone($lane->origin),
            'destination' => ZoneDocument::zone($lane->destination),
            'basis' => ZoneDocument::zone($basisZone === $origin ? $lane->origin : $lane->destination),
        ];
        $evidence['matrix_cell_id'] = $matrixCell;
        $evidence['service_tariffs'] = array_map(self::serviceTariff(...), $resolution->dependencyEvidence);
        $evidence['selection'] = (bool) ($tariff->is_default ?? false) ? 'SERVICE_DEFAULT' : 'LEGACY_PRIORITY';

        return $evidence;
    }

    private static function serviceTariff(ServiceTariffEvidenceDto $evidence): array
    {
        return [
            'tariff_family_id' => $evidence->familyId,
            'tariff_version_id' => $evidence->versionId,
            'version_number' => $evidence->versionNumber,
            'charge_code' => $evidence->chargeCode,
            'applied' => $evidence->applied,
            'zone_set_version_id' => $evidence->zoneSetVersionId,
        ];
    }
}
