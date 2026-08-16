<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Consignment\Application\ConsignmentService;
use Modules\Consignment\Application\PricingService;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConsignmentController
{
    private const DRAFT_FIELDS = [
        'sender', 'receiver', 'service_type_id', 'shipping_method_id',
        'service_offering_id', 'service_offering_version_id',
        'selected_option_version_ids',
        'pickup_service_date', 'pickup_window_code', 'delivery_window_code',
        'pickup_commitment_at', 'delivery_commitment_at', 'weight_kg',
        'width_cm', 'length_cm', 'height_cm', 'declared_value_amount',
        'insurance_enabled', 'insurance_value_amount', 'cod_enabled',
        'cod_amount', 'payer', 'payment_method', 'parcels',
    ];

    private const CONTACT_FIELDS = [
        'address_book_entry_id', 'contact_name', 'mobile', 'phone',
        'address_text', 'country', 'state', 'city', 'city_id', 'postal_code',
        'latitude', 'longitude',
    ];

    private const PARCEL_FIELDS = ['content_description', 'weight_kg', 'width_cm', 'length_cm', 'height_cm'];

    public function __construct(
        private ConsignmentService $consignments,
        private PricingService $pricing,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'status' => ['sometimes', 'nullable', 'in:D00,CFM,PD,PU,IR,ROU,OF,OS,OD,OK,NPU,NOK,RH,RCH,RO,AA'],
            'status_group' => ['sometimes', 'nullable', 'in:NEW_ROUTED,UNASSIGNED,ASSIGNED,IN_OPERATION,EXCEPTION,COMPLETED,CANCELLED'],
            'pickup_node_id' => ['sometimes', 'nullable', 'uuid'],
            'delivery_node_id' => ['sometimes', 'nullable', 'uuid'],
            'service_type_id' => ['sometimes', 'nullable', 'uuid'],
            'shipping_method_id' => ['sometimes', 'nullable', 'uuid'],
            'created_from' => ['sometimes', 'nullable', 'date'],
            'created_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['sometimes', 'in:created_at,-created_at,updated_at,-updated_at,consignment_number,-consignment_number,current_status,-current_status'],
        ]);
        $paginator = $this->consignments->list(
            $this->principal($request),
            $this->nodeId($request),
            $filters,
        );
        $statusCounts = $this->consignments->statusGroupCounts(
            $this->principal($request),
            $this->nodeId($request),
            $filters,
        );

        return ApiResponder::paginated(
            $request,
            $paginator,
            fn ($row): array => $this->consignments->listItem((array) $row),
            ['status_counts' => $statusCounts],
        );
    }

    public function quote(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, [...self::DRAFT_FIELDS, 'purpose', 'consignment_id', 'expected_version']);
        $this->assertNestedPayload($request);
        $input = $request->validate([
            ...$this->draftRules(),
            'purpose' => ['required', 'in:CREATE,EDIT'],
            'consignment_id' => ['required_if:purpose,EDIT', 'nullable', 'uuid'],
            'expected_version' => ['required_if:purpose,EDIT', 'nullable', 'integer', 'min:1'],
        ]);
        $purpose = (string) $input['purpose'];
        $consignmentId = isset($input['consignment_id']) ? (string) $input['consignment_id'] : null;
        $expectedVersion = isset($input['expected_version']) ? (int) $input['expected_version'] : null;
        unset($input['purpose'], $input['consignment_id'], $input['expected_version']);
        if ($purpose === 'CREATE') $input = $this->normalizePilotCreate($input);

        return ApiResponder::success($request, $this->pricing->calculate(
            $this->principal($request),
            $this->nodeId($request),
            $purpose,
            $input,
            $consignmentId,
            $expectedVersion,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, [...self::DRAFT_FIELDS, 'accepted_quote']);
        $this->assertNestedPayload($request, true);
        $input = $request->validate([
            ...$this->draftRules(),
            ...$this->acceptedQuoteRules(),
        ]);
        $input = $this->normalizePilotCreate($input);

        return ApiResponder::success($request, $this->consignments->create(
            $this->principal($request),
            $this->nodeId($request),
            $input,
            $this->correlationId($request),
        ), status: 201);
    }

    public function show(Request $request, string $consignmentId): JsonResponse
    {
        return ApiResponder::success($request, $this->consignments->get(
            $this->principal($request),
            $this->nodeId($request),
            $consignmentId,
        ));
    }

    public function update(Request $request, string $consignmentId): JsonResponse
    {
        $allowed = [
            'expected_version', 'change_reason', 'note', 'sender', 'receiver',
            'service_type_id', 'shipping_method_id', 'pickup_commitment_at',
            'delivery_commitment_at', 'weight_kg', 'width_cm', 'length_cm',
            'height_cm', 'declared_value_amount', 'insurance_enabled',
            'insurance_value_amount', 'cod_enabled', 'cod_amount',
            'accepted_quote',
        ];
        StrictPayload::assertOnly($request, $allowed);
        $this->assertContactOnly($request->input('sender'), 'sender');
        $this->assertContactOnly($request->input('receiver'), 'receiver');
        $this->assertAcceptedQuoteOnly($request->input('accepted_quote'));
        $input = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'change_reason' => ['required', 'string', 'max:160'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ...$this->contactRules('sender', false),
            ...$this->contactRules('receiver', false),
            'service_type_id' => ['sometimes', 'uuid'],
            'shipping_method_id' => ['sometimes', 'uuid'],
            'pickup_commitment_at' => ['sometimes', 'nullable', 'date'],
            'delivery_commitment_at' => ['sometimes', 'nullable', 'date'],
            'weight_kg' => ['sometimes', 'numeric', 'gt:0'],
            'width_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'length_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'declared_value_amount' => ['sometimes', 'integer', 'min:0'],
            'insurance_enabled' => ['sometimes', 'boolean'],
            'insurance_value_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cod_enabled' => ['sometimes', 'boolean'],
            'cod_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            ...$this->acceptedQuoteRules(false),
        ]);
        if (count(array_diff(array_keys($input), ['expected_version', 'change_reason', 'note', 'accepted_quote'])) === 0) {
            throw ValidationException::withMessages([
                'changes' => ['At least one editable field is required.'],
            ]);
        }

        return ApiResponder::success($request, $this->consignments->edit(
            $this->principal($request),
            $this->nodeId($request),
            $consignmentId,
            $input,
            $this->correlationId($request),
        ));
    }

    /** @return array<string, list<string>> */
    private function draftRules(): array
    {
        return [
            ...$this->contactRules('sender', true),
            ...$this->contactRules('receiver', true),
            'service_type_id' => ['required_without:service_offering_id', 'nullable', 'uuid'],
            'shipping_method_id' => ['required_without:service_offering_id', 'nullable', 'uuid'],
            'service_offering_id' => ['sometimes', 'nullable', 'uuid'],
            'service_offering_version_id' => ['sometimes', 'nullable', 'uuid'],
            'selected_option_version_ids' => ['sometimes', 'array'],
            'selected_option_version_ids.*' => ['uuid'],
            'pickup_service_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'pickup_window_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'delivery_window_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'pickup_commitment_at' => ['sometimes', 'nullable', 'date'],
            'delivery_commitment_at' => ['sometimes', 'nullable', 'date'],
            'weight_kg' => ['required', 'numeric', 'gt:0'],
            'width_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'length_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'declared_value_amount' => ['required', 'integer', 'min:0'],
            'insurance_enabled' => ['required', 'boolean'],
            'insurance_value_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cod_enabled' => ['required', 'boolean'],
            'cod_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'payer' => ['required', 'in:SENDER,RECEIVER,VENDOR'],
            'payment_method' => ['required', 'in:CASH,CREDIT,COD'],
            'parcels' => ['required', 'array', 'min:1', 'max:100'],
            'parcels.*.content_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'parcels.*.weight_kg' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.width_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.length_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.height_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    /** @return array<string, list<string>> */
    private function contactRules(string $prefix, bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            $prefix => [$presence, 'array'],
            "{$prefix}.address_book_entry_id" => ['sometimes', 'nullable', 'uuid'],
            "{$prefix}.contact_name" => [$required ? 'required' : 'sometimes', 'string', 'max:200'],
            "{$prefix}.mobile" => [$required ? 'required' : 'sometimes', 'string', 'max:32'],
            "{$prefix}.phone" => ['sometimes', 'nullable', 'string', 'max:32'],
            "{$prefix}.address_text" => [$required ? 'required' : 'sometimes', 'string', 'max:1000'],
            "{$prefix}.country" => ['sometimes', 'nullable', 'string', 'max:120'],
            "{$prefix}.state" => ['sometimes', 'nullable', 'string', 'max:160'],
            "{$prefix}.city" => ['sometimes', 'nullable', 'string', 'max:160'],
            "{$prefix}.city_id" => [$required ? 'required' : 'sometimes', 'uuid'],
            "{$prefix}.postal_code" => ['sometimes', 'nullable', 'string', 'max:32'],
            "{$prefix}.latitude" => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            "{$prefix}.longitude" => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, list<string>> */
    private function acceptedQuoteRules(bool $required = true): array
    {
        return [
            'accepted_quote' => [$required ? 'required' : 'sometimes', 'array'],
            'accepted_quote.quote_id' => [$required ? 'required' : 'required_with:accepted_quote', 'uuid'],
            'accepted_quote.quote_version' => [$required ? 'required' : 'required_with:accepted_quote', 'integer', 'min:1'],
            'accepted_quote.option_id' => [$required ? 'required' : 'required_with:accepted_quote', 'uuid'],
        ];
    }

    private function assertNestedPayload(Request $request, bool $accepted = false): void
    {
        $this->assertContactOnly($request->input('sender'), 'sender');
        $this->assertContactOnly($request->input('receiver'), 'receiver');
        if (is_array($request->input('parcels'))) {
            StrictPayload::assertItemsOnly($request->input('parcels'), self::PARCEL_FIELDS, 'parcels');
        }
        if ($accepted) {
            $this->assertAcceptedQuoteOnly($request->input('accepted_quote'));
        }
    }

    private function assertContactOnly(mixed $contact, string $field): void
    {
        if (is_array($contact)) {
            StrictPayload::assertItemsOnly([$contact], self::CONTACT_FIELDS, $field);
        }
    }

    private function assertAcceptedQuoteOnly(mixed $quote): void
    {
        if (is_array($quote)) {
            StrictPayload::assertItemsOnly(
                [$quote],
                ['quote_id', 'quote_version', 'option_id'],
                'accepted_quote',
            );
        }
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function normalizePilotCreate(array $input): array
    {
        if (($input['insurance_enabled'] ?? null) !== true) {
            throw ValidationException::withMessages(['insurance_enabled' => ['Insurance is mandatory for new pilot Consignments.']]);
        }
        if (! in_array($input['payer'] ?? null, ['SENDER', 'RECEIVER'], true)) {
            throw ValidationException::withMessages(['payer' => ['Only sender or receiver payer is available for new pilot Consignments.']]);
        }
        if (! in_array($input['payment_method'] ?? null, ['CASH', 'CREDIT'], true)) {
            throw ValidationException::withMessages(['payment_method' => ['Only cash or credit is available for new pilot Consignments.']]);
        }
        foreach ((array) ($input['parcels'] ?? []) as $index => $parcel) {
            if (trim((string) ($parcel['content_description'] ?? '')) === '') {
                throw ValidationException::withMessages(["parcels.{$index}.content_description" => ['Parcel content description is required.']]);
            }
        }
        $input['insurance_enabled'] = true;
        $input['insurance_value_amount'] = (int) $input['declared_value_amount'];
        return $input;
    }

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function nodeId(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'An active operational node is required.',
                ['X-Node-Id' => ['The operational node header is required.']],
            );
        }

        return $nodeId;
    }

    private function correlationId(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
}
