<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\IndustryRecord;

/** @mixin IndustryRecord */
final class CustomerIndustryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['industry_id' => $this->industry_id, 'title' => $this->title];
    }
}
