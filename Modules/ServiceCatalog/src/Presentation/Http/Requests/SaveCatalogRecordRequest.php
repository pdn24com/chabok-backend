<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\ServiceCatalog\Application\Services\CatalogRecordAccessGuard;

final class SaveCatalogRecordRequest extends FormRequest
{
    public function authorize(CatalogRecordAccessGuard $access): bool
    {
        $access->authorize($this->attributes->get('principal'), true);
        return true;
    }

    public function rules(): array
    {
        $resource = (string) $this->route('resource');
        $identityId = $this->route('identityId');
        $rules = $resource === 'commitment-schedules' ? CatalogRequestRules::scheduleRules($identityId === null) : CatalogRequestRules::draftRules($resource, $identityId === null);
        // Legacy timestamps are read-only evidence, never scheduling inputs for current records.
        unset($rules['valid_from'], $rules['valid_to']);
        if ($identityId === null) {
            $rules['code'] = ['sometimes', 'nullable', 'regex:/^[0-9]{6}$/'];
        }
        if ($resource === 'offerings') {
            foreach (['service_type', 'shipping_method'] as $prefix) {
                unset($rules[$prefix . '_version_id']);
                $rules[$prefix . '_id'] = ['required', 'uuid'];
            }
            unset($rules['option_rules.*.service_option_version_id'], $rules['commitment_binding.commitment_schedule_version_id']);
            $rules['option_rules.*.service_option_id'] = ['required', 'uuid'];
            $rules['commitment_binding.commitment_schedule_id'] = ['required_with:commitment_binding', 'uuid'];
        }
        if ($identityId !== null) {
            $rules['expected_version'] = ['required', 'integer', 'min:1'];
        }
        return $rules;
    }

    public function catalogInput(): array
    {
        $resource = (string) $this->route('resource');
        $input = $this->validated();
        if ($resource === 'offerings') {
            foreach (['service_type', 'shipping_method'] as $prefix) {
                $input[$prefix . '_version_id'] = $input[$prefix . '_id'];
            }
            foreach ($input['option_rules'] ?? [] as $index => $rule) {
                $input['option_rules'][$index]['service_option_version_id'] = $rule['service_option_id'];
            }
            if (!empty($input['commitment_binding'])) {
                $input['commitment_binding']['commitment_schedule_version_id'] = $input['commitment_binding']['commitment_schedule_id'];
            }
        }
        return $input;
    }
}
