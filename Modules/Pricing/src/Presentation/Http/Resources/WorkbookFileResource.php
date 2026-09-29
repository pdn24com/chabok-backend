<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Application\Dto\WorkbookFileDto;

/** @mixin WorkbookFileDto */
final class WorkbookFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['filename' => $this->filename, 'content_base64' => $this->contentBase64];
    }
}
