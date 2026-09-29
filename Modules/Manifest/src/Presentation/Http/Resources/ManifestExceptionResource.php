<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Manifest\Application\Serialization\ManifestWorkflowDocument;

final class ManifestExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mapped = $this->cases->map((new ManifestWorkflowDocument)->caseResource(...))->all();

        return ['manifest_id' => $this->manifest->manifest_id, 'manifest_state' => $this->manifest->state->value,
            'manifest_version' => (int) $this->manifest->version, 'current_exception' => $mapped[0] ?? null, 'previous_attempts' => array_slice($mapped, 1)];
    }
}
