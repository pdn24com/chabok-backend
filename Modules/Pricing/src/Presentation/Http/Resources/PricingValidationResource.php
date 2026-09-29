<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pricing\Domain\ValueObjects\PricingValidationResult;

/** @mixin PricingValidationResult */
final class PricingValidationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $errors = [];
        foreach ($this->errors as $issue) {
            $error = ['code' => $issue->code->value, 'field' => $issue->field];
            if ($issue->message !== null) {
                $error['message'] = $issue->message;
            }
            $errors[] = $error;
        }

        return ['valid' => $this->valid, 'errors' => $errors];
    }
}
