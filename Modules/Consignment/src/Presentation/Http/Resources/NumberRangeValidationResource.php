<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Consignment\Application\UseCases\ValidateNumberRange\ValidateNumberRangeResult;

final class NumberRangeValidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ValidateNumberRangeResult $result */
        $result = $this->resource;
        $preview = $result->preview;

        return [
            'numeric_prefix' => $preview->numericPrefix,
            'total_length' => $preview->totalLength,
            'serial_width' => $preview->serialWidth,
            'serial_start' => $preview->serialStart,
            'serial_end' => $preview->serialEnd,
            'first_number' => $preview->firstNumber,
            'last_number' => $preview->lastNumber,
            'total_capacity' => $preview->totalCapacity,
            'sample_first_values' => $preview->sampleFirstValues,
            'sample_final_values' => $preview->sampleFinalValues,
            'overlaps_existing_range' => $result->overlapsExistingRange,
            'validation_result' => $result->overlapsExistingRange ? 'OVERLAP' : 'VALID',
        ];
    }
}
