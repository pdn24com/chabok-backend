<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Foundation\Presentation\Http\StrictPayload;

abstract class OperationalStatusRequest extends FormRequest
{
    abstract protected function updating(): bool;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title_fa' => 'required|string|max:200',
            'title_en' => 'nullable|string|max:200',
            'partial_title_fa' => 'nullable|string|max:200',
            'partial_title_en' => 'nullable|string|max:200',
            'tone' => 'required|in:neutral,info,brand,warning,danger,success,ink',
            'status_group' => 'nullable|in:NEW_ROUTED,IN_OPERATION,EXCEPTION,COMPLETED,CANCELLED',
            'is_terminal' => 'required|boolean',
            'is_active' => 'required|boolean',
            'sort_order' => 'required|integer|min:0|max:10000',
        ];
        $rules += $this->updating() ? ['expected_version' => 'required|integer|min:1'] : ['code' => 'required|string|regex:/^[A-Z][A-Z0-9_]{1,31}$/', 'scope' => 'required|in:GLOBAL,TENANT'];
        return $rules;
    }

    protected function prepareForValidation(): void
    {
        StrictPayload::assertOnly($this, array_keys($this->rules()));
    }
}
