<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ServiceCatalog\Application\Serialization\CatalogDocument;
use Modules\ServiceCatalog\Application\Serialization\EligibilityDecisionDocument;
use Modules\ServiceCatalog\Application\Serialization\OfferingCommitmentDocument;
use Modules\ServiceCatalog\Application\Serialization\SelectedServiceOptionDocument;

final class ResolvedServiceOfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...CatalogDocument::offering($this->version, $this->dependencies), ...EligibilityDecisionDocument::make($this->decision),
            'options' => array_map(SelectedServiceOptionDocument::serialize(...), $this->options), 'commitment' => $this->commitment === null ? null : OfferingCommitmentDocument::serialize($this->commitment)];
    }
}
