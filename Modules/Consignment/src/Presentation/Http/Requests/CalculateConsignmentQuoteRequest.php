<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\StrictPayload;

final class CalculateConsignmentQuoteRequest extends ConsignmentInputRequest
{
    public function rules(): array
    {
        return [
            ...$this->draftRules(),
            'purpose' => ['required', 'in:CREATE,EDIT'],
            'consignment_id' => ['required_if:purpose,EDIT', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'expected_version' => ['required_if:purpose,EDIT', 'nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, [...self::DRAFT_FIELDS, 'purpose', 'consignment_id', 'expected_version']);
        $this->assertNestedPayload($this);
    }
}
