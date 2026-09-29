<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\StrictPayload;

final class CreateConsignmentRequest extends ConsignmentInputRequest
{
    public function rules(): array
    {
        return [...$this->draftRules(), ...$this->acceptedQuoteRules()];
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, [...self::DRAFT_FIELDS, 'accepted_quote']);
        $this->assertNestedPayload($this, true);
    }
}
