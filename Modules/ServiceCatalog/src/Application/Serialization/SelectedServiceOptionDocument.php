<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Dto\SelectedServiceOptionDto;

final class SelectedServiceOptionDocument
{
    public static function serialize(SelectedServiceOptionDto $option): array
    {
        return [
            'service_option_id' => $option->optionId,
            'service_option_version_id' => $option->versionId,
            'code' => $option->code,
            'labels' => $option->labels,
            'definition' => $option->definition,
            'compatibility' => $option->compatibility,
            'required' => $option->required,
            'selectable' => $option->selectable,
            'reason_code' => $option->reasonCode,
        ];
    }
}
