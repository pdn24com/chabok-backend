<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Customer\Domain\Enums\ContactPointIdentifierKind;
use Modules\Customer\Domain\Enums\ContactPointScope;
use Modules\Customer\Domain\Enums\ContactPointStatus;
use Modules\Customer\Domain\Enums\ContactPointType;
use Modules\Foundation\Presentation\Http\Requests\ApiFormRequest;

final class SaveCustomerContactPointsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The body is the complete set the person should hold; an empty list removes every channel.
            'items' => ['present', 'array', 'max:50'],
            'items.*' => ['array'],
            'items.*.id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'items.*.type' => ['required', Rule::enum(ContactPointType::class)],
            'items.*.identifier_kind' => ['required', Rule::enum(ContactPointIdentifierKind::class)],
            // The value is always sent, even for an address reference, where it is the label the operator sees.
            'items.*.value' => ['required', 'string', 'max:320'],
            'items.*.scope' => ['required', Rule::enum(ContactPointScope::class)],
            'items.*.is_default' => ['sometimes', 'boolean'],
            'items.*.status' => ['sometimes', Rule::enum(ContactPointStatus::class)],
            'items.*.priority' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'items.*.subtype' => ['nullable', 'string', 'max:80'],
            'items.*.work_context' => ['nullable', 'string', 'max:200'],
            'items.*.relationship_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'items.*.address_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'items.*.verified_manually' => ['sometimes', 'boolean'],
        ];
    }
}
