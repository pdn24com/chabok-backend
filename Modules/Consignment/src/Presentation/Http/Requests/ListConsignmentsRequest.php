<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesCommand;
use Modules\Consignment\Application\UseCases\ListOperationalStatusCodes\ListOperationalStatusCodesHandler;
use Modules\Foundation\Presentation\Http\ListSelections;

final class ListConsignmentsRequest extends ConsignmentInputRequest
{
    public function rules(ListOperationalStatusCodesHandler $statuses): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'array', 'max:50'],
            'status.*' => ['required', Rule::in($statuses->handle(new ListOperationalStatusCodesCommand($this->attributes->get('principal')->hqId)))],
            'status_group' => ['sometimes', 'nullable', 'in:NEW_ROUTED,UNASSIGNED,ASSIGNED,IN_OPERATION,EXCEPTION,COMPLETED,CANCELLED'],
            'sla_risk' => ['sometimes', 'array', 'max:50'],
            'sla_risk.*' => ['required', 'in:OVERDUE,AT_RISK,ON_TIME,NO_COMMITMENT'],
            'pickup_node_id' => ['sometimes', 'array', 'max:50'],
            'pickup_node_id.*' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'delivery_node_id' => ['sometimes', 'array', 'max:50'],
            'delivery_node_id.*' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'pickup_man_id' => ['sometimes', 'array', 'max:50'],
            'pickup_man_id.*' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'delivery_man_id' => ['sometimes', 'array', 'max:50'],
            'delivery_man_id.*' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'service_type_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'shipping_method_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:created_from'],
            'sort' => [
                'sometimes',
                'in:created_at,-created_at,updated_at,-updated_at,consignment_number,-consignment_number,current_status,-current_status',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        ListSelections::normalize($this, ['status', 'sla_risk', 'pickup_node_id', 'delivery_node_id', 'pickup_man_id', 'delivery_man_id']);
    }
}
