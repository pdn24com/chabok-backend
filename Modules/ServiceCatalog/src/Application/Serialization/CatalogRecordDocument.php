<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Dto\CatalogRecordDetailDto;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

/** Shared immutable audit/HTTP document. All reference relations are preloaded. */
final class CatalogRecordDocument
{
    public static function make(CatalogRecordDetailDto $record): array
    {
        $version = $record->version;
        $detail = $version instanceof CommitmentScheduleVersionRecord ? ScheduleDocument::version($version) : CatalogDocument::version($version);
        if ($version instanceof ServiceOfferingVersionRecord) {
            $detail['service_type_id'] = $version->serviceTypeVersion?->service_type_id;
            $detail['shipping_method_id'] = $version->shippingMethodVersion?->shipping_method_id;
            if ($version->commitmentBinding !== null) {
                $detail['commitment_binding']['commitment_schedule_id'] = $version->commitmentBinding->scheduleVersion?->commitment_schedule_id;
            }
            foreach ($version->optionRules as $index => $rule) {
                $detail['option_rules'][$index]['service_option_id'] = $rule->optionVersion?->service_option_id;
            }
        }

        return [...$detail, 'status' => $record->identity->status, 'lock_version' => (int) $record->identity->edit_lock];
    }
}
