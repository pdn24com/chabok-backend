<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;

/** @mixin ContactPointRecord */
final class ContactPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'contact_point_id' => $this->contact_point_id,
            'type' => $this->type,
            'identifier_kind' => $this->identifier_kind,
            'value' => $this->value,
            'normalized_value' => $this->normalized_value,
            'scope' => $this->scope,
            'is_default' => $this->is_default,
            'status' => $this->status,
            'priority' => $this->priority === null ? null : (int) $this->priority,
            'subtype' => $this->subtype,
            'work_context' => $this->work_context,
            'relationship_id' => $this->relationship_id,
            'address_id' => $this->address_id,
            'verified_manually_at' => $this->verified_manually_at?->toISOString(),
        ];
    }
}
