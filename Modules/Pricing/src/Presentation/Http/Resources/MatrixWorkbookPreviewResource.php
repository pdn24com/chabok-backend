<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewResultDto;
use Modules\Pricing\Application\Serialization\MatrixDraftSerializer;

/** @mixin MatrixWorkbookPreviewResultDto */
final class MatrixWorkbookPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'matrix' => MatrixDraftSerializer::serialize($this->resource),
            'band_count' => count($this->bands),
            'linear_band_count' => count($this->linearBands),
            'zone_count' => count($this->matrix->zoneIds),
        ];
    }
}
