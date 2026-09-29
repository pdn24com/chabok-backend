<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Authorization\Application\Serialization\RoleDocument;

final class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return RoleDocument::serialize($this->resource);
    }
}
