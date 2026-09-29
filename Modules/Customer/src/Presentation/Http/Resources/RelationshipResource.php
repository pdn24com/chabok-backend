<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Application\Dto\CustomerRelationshipDto;

/** @mixin CustomerRelationshipDto */
final class RelationshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'relationship_id' => $this->relationshipId,
            'person' => ['customer_id' => $this->personCustomerId, 'display_name' => $this->personDisplayName],
            'company' => ['customer_id' => $this->companyCustomerId, 'display_name' => $this->companyDisplayName],
            'position' => $this->positionId === null ? null : ['position_id' => $this->positionId, 'title' => $this->positionTitle],
            'role_title' => $this->roleTitle,
            'decision_level' => $this->decisionLevel,
            'signing_authority' => $this->signingAuthority,
            'valid_from' => $this->validFrom?->format('Y-m-d'),
            'valid_to' => $this->validTo?->format('Y-m-d'),
            'is_primary' => $this->isPrimary,
        ];
    }
}
