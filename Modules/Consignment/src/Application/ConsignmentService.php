<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Consignment\Domain\ConsignmentPolicy;
use Modules\Consignment\Infrastructure\Persistence\ConsignmentNumberAllocator;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Geography\Application\GeographyResolver;

final readonly class ConsignmentService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private PricingService $pricing,
        private ConsignmentPolicy $policy,
        private ConsignmentNumberAllocator $numbers,
        private GeographyResolver $geography,
    ) {}

    /** @param array<string, mixed> $filters */
    public function list(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $filters,
    ): LengthAwarePaginator {
        $this->assertAccess($actor, $nodeId, 'consignment.view');
        $query = DB::table('consignments as c')
            ->join('nodes as pickup_node', function ($join): void {
                $join->on('pickup_node.node_id', '=', 'c.pickup_node_id')
                    ->on('pickup_node.hq_id', '=', 'c.hq_id');
            })
            ->leftJoin('nodes as delivery_node', function ($join): void {
                $join->on('delivery_node.node_id', '=', 'c.delivery_node_id')
                    ->on('delivery_node.hq_id', '=', 'c.hq_id');
            })
            ->where('c.hq_id', $actor->hqId)
            ->where(fn (Builder $query) => $this->applyNodeVisibility($query, $nodeId))
            ->select([
                'c.consignment_id', 'c.consignment_number',
                'c.receiver_contact_name', 'c.receiver_mobile', 'c.receiver_address_text',
                'c.pickup_node_id', 'pickup_node.node_title as pickup_node_title',
                'c.delivery_node_id', 'delivery_node.node_title as delivery_node_title',
                'c.pickup_man_id', 'c.delivery_man_id', 'c.current_status',
                'c.aggregate_mode', 'c.parcel_status_counts',
                'c.version', 'c.created_at', 'c.updated_at',
            ])->selectSub(
                DB::table('parcels as p')->selectRaw('COUNT(*)')
                    ->whereColumn('p.consignment_id', 'c.consignment_id')
                    ->whereColumn('p.hq_id', 'c.hq_id'),
                'parcel_count',
            )->selectSub(
                DB::table('consignment_pricing_versions as cpv')->select('cpv.total_amount')
                    ->whereColumn('cpv.consignment_id', 'c.consignment_id')
                    ->whereColumn('cpv.hq_id', 'c.hq_id')
                    ->orderByDesc('cpv.version_number')->limit(1),
                'payable_total_amount',
            )->selectSub(
                DB::table('consignment_pricing_versions as cpv')->select('cpv.currency')
                    ->whereColumn('cpv.consignment_id', 'c.consignment_id')
                    ->whereColumn('cpv.hq_id', 'c.hq_id')
                    ->orderByDesc('cpv.version_number')->limit(1),
                'payable_currency',
            );
        $this->applyFilters($query, $filters);
        [$sortField, $sortDirection] = $this->sort((string) ($filters['sort'] ?? '-created_at'));
        $query->orderBy("c.{$sortField}", $sortDirection)->orderBy('c.consignment_id', $sortDirection);

        return $query->paginate(
            perPage: (int) ($filters['page_size'] ?? 25),
            page: (int) ($filters['page'] ?? 1),
        );
    }

    /**
     * Counts are tenant- and selected-node-scoped and honor non-status filters.
     *
     * @param array<string, mixed> $filters
     * @return array<string, int>
     */
    public function statusGroupCounts(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $filters,
    ): array {
        $this->assertAccess($actor, $nodeId, 'consignment.view');
        unset($filters['status'], $filters['status_group'], $filters['page'], $filters['page_size'], $filters['sort']);
        $query = DB::table('consignments as c')
            ->where('c.hq_id', $actor->hqId)
            ->where(fn (Builder $query) => $this->applyNodeVisibility($query, $nodeId));
        $this->applyFilters($query, $filters);
        $row = (array) $query->selectRaw(
            "COUNT(*) AS total,
            SUM(CASE WHEN c.current_status IN ('CFM','PD') THEN 1 ELSE 0 END) AS new_routed,
            SUM(CASE WHEN c.pickup_man_id IS NULL AND c.delivery_man_id IS NULL THEN 1 ELSE 0 END) AS unassigned,
            SUM(CASE WHEN c.pickup_man_id IS NOT NULL OR c.delivery_man_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned,
            SUM(CASE WHEN c.current_status IN ('PU','IR','ROU','OF','OS','CI','OD') THEN 1 ELSE 0 END) AS in_operation,
            SUM(CASE WHEN c.current_status IN ('NPU','NOK','RH','RCH') THEN 1 ELSE 0 END) AS exception,
            SUM(CASE WHEN c.current_status = 'OK' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN c.current_status IN ('RO','AA') THEN 1 ELSE 0 END) AS cancelled",
        )->first();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'new_routed' => (int) ($row['new_routed'] ?? 0),
            'unassigned' => (int) ($row['unassigned'] ?? 0),
            'assigned' => (int) ($row['assigned'] ?? 0),
            'in_operation' => (int) ($row['in_operation'] ?? 0),
            'exception' => (int) ($row['exception'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    public function get(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
    ): array {
        $context = $this->assertAccess($actor, $nodeId, 'consignment.view');
        $row = $this->visibleQuery($actor, $context)
            ->where('c.consignment_id', $consignmentId)->first();
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }

        return $this->detail((array) $row, $context);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        array $input,
        string $correlationId,
    ): array {
        $context = $this->assertAccess($actor, $nodeId, 'consignment.create');
        $acceptedInput = (array) $input['accepted_quote'];
        unset($input['accepted_quote']);
        foreach (['sender', 'receiver'] as $party) {
            $input[$party] = $this->geography->canonicalizeContact((array) $input[$party], true);
        }
        $this->policy->assertCommercialConsistency($input);
        $this->policy->assertPilotCreate($input);
        if (($input['delivery_node_id'] ?? null) !== null && ! DB::table('nodes')->where(['hq_id' => $actor->hqId, 'node_id' => $input['delivery_node_id'], 'status' => 'ACTIVE'])->exists()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The final delivery node is not active or does not belong to the tenant.');
        }
        $accepted = $this->pricing->accept($actor, $nodeId, 'CREATE', $input, $acceptedInput);
        if (($accepted['provider_code'] ?? null) === 'INTERNAL') {
            $input['service_type_id'] = $accepted['service_type_id'];
            $input['shipping_method_id'] = $accepted['shipping_method_id'];
            $input['service_offering_id'] = $accepted['service_offering_id'];
            $input['service_offering_version_id'] = $accepted['service_offering_version_id'];
            $input['selected_option_version_ids'] = $accepted['selected_option_version_ids'];
            $input = $this->withAcceptedCommitment($input, $accepted['commitment'] ?? null);
        } elseif (($input['service_offering_id'] ?? null) !== null) {
            throw new ApiException(ApiErrorCode::PricingRejected, 422, 'Pilot Service Offering creation requires internal versioned Pricing.', details: ['reason_code' => 'INTERNAL_PRICING_REQUIRED']);
        }
        $consignmentId = $this->transactions->run(function () use (
            $actor,
            $nodeId,
            $input,
            $accepted,
            $correlationId,
        ): string {
            $id = (string) Str::uuid();
            $allocation = $this->numbers->next((string) $actor->hqId);
            $number = $allocation['consignment_number'];
            $now = now();
            $parcels = (array) $input['parcels'];
            DB::table('consignments')->insert([
                'consignment_id' => $id,
                'hq_id' => $actor->hqId,
                'consignment_number' => $number,
                'initiator_id' => $actor->userId,
                'pickup_node_id' => $nodeId,
                'delivery_node_id' => $input['delivery_node_id'] ?? null,
                ...$this->contactColumns('sender', (array) $input['sender']),
                ...$this->contactColumns('receiver', (array) $input['receiver']),
                ...$this->commercialColumns($input),
                'current_status' => 'CFM',
                'aggregate_mode' => 'FULL',
                'parcel_status_counts' => json_encode(['CFM' => count($parcels)], JSON_THROW_ON_ERROR),
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->numbers->record((string) $actor->hqId, $allocation['range_id'], $id, $number, $actor->userId, $correlationId);
            foreach (array_values($parcels) as $index => $parcelInput) {
                $parcelId = (string) Str::uuid();
                DB::table('parcels')->insert([
                    'parcel_id' => $parcelId,
                    'hq_id' => $actor->hqId,
                    'consignment_id' => $id,
                    'parcel_number' => sprintf('%s-%02d', $number, $index + 1),
                    'current_status' => 'CFM',
                    'current_node_id' => $nodeId,
                    'current_custody_type' => 'NODE',
                    'current_custodian_id' => $nodeId,
                    'content_description' => trim((string) ($parcelInput['content_description'] ?? '')) ?: null,
                    ...$this->parcelPhysical((array) $parcelInput, $input),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('parcel_custody_events')->insert([
                    'custody_event_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
                    'event_sequence' => $this->nextSequence('parcel_custody_events', $id),
                    'consignment_id' => $id, 'parcel_id' => $parcelId,
                    'from_node_id' => null, 'to_node_id' => $nodeId,
                    'from_custody_type' => null, 'to_custody_type' => 'NODE',
                    'from_custodian_id' => null, 'to_custodian_id' => $nodeId,
                    'command_name' => 'CONSIGNMENT_CREATED', 'initiator_id' => $actor->userId,
                    'created_at' => $now,
                ]);
                $this->insertStatusEvent(
                    (string) $actor->hqId,
                    $id,
                    $parcelId,
                    $actor->userId,
                    $nodeId,
                    null,
                    'CFM',
                    'CONSIGNMENT_CONFIRMED',
                    $correlationId,
                );
            }
            $this->insertStatusEvent(
                (string) $actor->hqId,
                $id,
                null,
                $actor->userId,
                $nodeId,
                null,
                'CFM',
                'CONSIGNMENT_CONFIRMED',
                $correlationId,
            );
            $pricingVersionId = $this->persistPricing(
                (string) $actor->hqId,
                $id,
                1,
                $actor->userId,
                $accepted,
            );
            $this->audit->write(
                $actor->hqId,
                $actor->userId,
                'CONSIGNMENT_CREATED',
                'CONSIGNMENT',
                $id,
                $correlationId,
                after: ['version' => 1, 'status' => 'CFM', 'parcel_count' => count($parcels)],
                sourceClient: 'BRANCH_PANEL',
            );
            $this->audit->write(
                $actor->hqId,
                $actor->userId,
                'CONSIGNMENT_PRICING_ACCEPTED',
                'CONSIGNMENT',
                $id,
                $correlationId,
                after: [
                    'pricing_version_id' => $pricingVersionId,
                    'quote_id' => $accepted['quote_id'],
                    'option_id' => $accepted['option_id'],
                ],
                sourceClient: 'BRANCH_PANEL',
            );
            $this->outbox->write($actor->hqId, 'CONSIGNMENT', $id, 'consignment.created', $correlationId, [
                'consignment_id' => $id,
                'version' => '1',
                'status' => 'CFM',
                'pricing_version_id' => $pricingVersionId,
            ]);
            $this->outbox->write($actor->hqId, 'CONSIGNMENT', $id, 'consignment.pricing.accepted', $correlationId, [
                'consignment_id' => $id,
                'version' => '1',
                'pricing_version_id' => $pricingVersionId,
            ]);

            return $id;
        });
        $this->pricing->consume((string) $accepted['quote_id']);

        return $this->get($actor, $nodeId, $consignmentId);
    }

    /** @param array<string, mixed> $changes
     *  @return array<string, mixed>
     */
    public function edit(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $consignmentId,
        array $changes,
        string $correlationId,
    ): array {
        $context = $this->assertAccess($actor, $nodeId, 'consignment.edit');
        $acceptedInput = isset($changes['accepted_quote']) ? (array) $changes['accepted_quote'] : null;
        $expectedVersion = (int) $changes['expected_version'];
        $changeReason = (string) $changes['change_reason'];
        $note = $changes['note'] ?? null;
        unset($changes['accepted_quote'], $changes['expected_version'], $changes['change_reason'], $changes['note']);
        $acceptedQuoteId = $acceptedInput === null ? null : (string) $acceptedInput['quote_id'];
        $this->transactions->run(function () use (
            $actor,
            $nodeId,
            $consignmentId,
            $changes,
            $expectedVersion,
            $acceptedInput,
            $changeReason,
            $note,
            $correlationId,
            $context,
        ): void {
            $row = $this->visibleQuery($actor, $context)
                ->where('c.consignment_id', $consignmentId)->lockForUpdate()->first();
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ((int) $row->version !== $expectedVersion) {
                throw new ApiException(
                    ApiErrorCode::VersionConflict,
                    409,
                    'The Consignment changed since it was loaded.',
                    details: ['current_version' => (int) $row->version],
                );
            }
            $this->policy->assertEditable((string) $row->current_status, (array) config('chabok.consignment.editable_statuses'));
            $draft = array_replace_recursive($this->draftFromRow((array) $row), $changes);
            foreach (['sender', 'receiver'] as $party) {
                $draft[$party] = $this->geography->canonicalizeContact((array) $draft[$party], false);
            }
            $this->policy->assertCommercialConsistency($draft);
            $accepted = $acceptedInput === null ? null : $this->pricing->accept(
                $actor,
                $nodeId,
                'EDIT',
                $draft,
                $acceptedInput,
                $consignmentId,
                $expectedVersion,
            );
            if (($accepted['provider_code'] ?? null) === 'INTERNAL') {
                $draft['service_type_id'] = $accepted['service_type_id'];
                $draft['shipping_method_id'] = $accepted['shipping_method_id'];
                $draft['service_offering_id'] = $accepted['service_offering_id'];
                $draft['service_offering_version_id'] = $accepted['service_offering_version_id'];
                $draft['selected_option_version_ids'] = $accepted['selected_option_version_ids'];
                $draft = $this->withAcceptedCommitment($draft, $accepted['commitment'] ?? null);
            }
            $newVersion = $expectedVersion + 1;
            DB::table('consignments')->where([
                'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId,
                'version' => $expectedVersion,
            ])->update([
                ...$this->contactColumns('sender', (array) $draft['sender']),
                ...$this->contactColumns('receiver', (array) $draft['receiver']),
                ...$this->commercialColumns($draft),
                ...($accepted === null ? ['commercial_pricing_state' => 'STALE'] : []),
                'version' => $newVersion,
                'updated_at' => now(),
            ]);
            $pricingVersionId = $accepted === null ? null : $this->persistPricing(
                (string) $actor->hqId,
                $consignmentId,
                $newVersion,
                $actor->userId,
                $accepted,
            );
            $this->audit->write(
                $actor->hqId,
                $actor->userId,
                'CONSIGNMENT_UPDATED',
                'CONSIGNMENT',
                $consignmentId,
                $correlationId,
                before: ['version' => $expectedVersion, 'status' => $row->current_status],
                after: ['version' => $newVersion, 'status' => $row->current_status],
                safeNote: $changeReason.($note ? ': '.$note : ''),
                sourceClient: 'BRANCH_PANEL',
            );
            $this->outbox->write($actor->hqId, 'CONSIGNMENT', $consignmentId, 'consignment.updated', $correlationId, array_filter([
                'consignment_id' => $consignmentId,
                'version' => (string) $newVersion,
                'status' => (string) $row->current_status,
                'pricing_version_id' => $pricingVersionId,
            ], static fn ($value) => $value !== null));
            $this->outbox->write($actor->hqId, 'CONSIGNMENT', $consignmentId, $accepted === null ? 'consignment.pricing.stale' : 'consignment.pricing.accepted', $correlationId, array_filter([
                'consignment_id' => $consignmentId,
                'version' => (string) $newVersion,
                'pricing_version_id' => $pricingVersionId,
            ], static fn ($value) => $value !== null));
        });
        if ($acceptedQuoteId !== null) $this->pricing->consume($acceptedQuoteId);

        return $this->get($actor, $nodeId, $consignmentId);
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    public function listItem(array $row): array
    {
        return [
            'consignment_id' => (string) $row['consignment_id'],
            'consignment_number' => (string) $row['consignment_number'],
            'receiver_contact_name' => (string) $row['receiver_contact_name'],
            'receiver_mobile' => (string) $row['receiver_mobile'],
            'receiver_address_text' => (string) $row['receiver_address_text'],
            'pickup_node_id' => $row['pickup_node_id'] ? (string) $row['pickup_node_id'] : null,
            'pickup_node_title' => (string) $row['pickup_node_title'],
            'delivery_node_id' => $row['delivery_node_id'] ? (string) $row['delivery_node_id'] : null,
            'delivery_node_title' => $row['delivery_node_title'] ? (string) $row['delivery_node_title'] : null,
            'pickup_man_id' => $row['pickup_man_id'] ? (string) $row['pickup_man_id'] : null,
            'delivery_man_id' => $row['delivery_man_id'] ? (string) $row['delivery_man_id'] : null,
            'current_status' => (string) $row['current_status'],
            'aggregate' => $this->aggregateResource($row),
            'parcel_count' => (int) $row['parcel_count'],
            'payable_total_amount' => $row['payable_total_amount'] === null ? null : (int) $row['payable_total_amount'],
            'payable_currency' => $row['payable_currency'] === null ? null : (string) $row['payable_currency'],
            'version' => (int) $row['version'],
            'created_at' => $this->time($row['created_at']),
            'updated_at' => $this->time($row['updated_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function assertAccess(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): array {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        $entitled = collect($context['module_entitlements'])
            ->contains(fn (array $item): bool => $item['module_code'] === 'Consignment'
                && $item['status'] === 'ENABLED');
        if (! $entitled) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (! in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        if (! in_array($nodeId, $context['accessible_node_ids'], true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'Access denied.');
        }

        return $context;
    }

    /** @param array<string, mixed> $context */
    private function visibleQuery(AuthenticatedPrincipal $actor, array $context): Builder
    {
        return DB::table('consignments as c')
            ->join('nodes as pickup_node', function ($join): void {
                $join->on('pickup_node.node_id', '=', 'c.pickup_node_id')
                    ->on('pickup_node.hq_id', '=', 'c.hq_id');
            })
            ->leftJoin('nodes as delivery_node', function ($join): void {
                $join->on('delivery_node.node_id', '=', 'c.delivery_node_id')
                    ->on('delivery_node.hq_id', '=', 'c.hq_id');
            })
            ->where('c.hq_id', $actor->hqId)
            ->where(function (Builder $query) use ($context): void {
                foreach ($context['accessible_node_ids'] as $index => $nodeId) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(fn (Builder $nodeQuery) => $this->applyNodeVisibility($nodeQuery, (string) $nodeId));
                }
            })
            ->select([
                'c.*',
                'pickup_node.node_title as pickup_node_title',
                'delivery_node.node_title as delivery_node_title',
            ])
            ->selectSub(
                DB::table('parcels as p')->selectRaw('COUNT(*)')
                    ->whereColumn('p.consignment_id', 'c.consignment_id')
                    ->whereColumn('p.hq_id', 'c.hq_id'),
                'parcel_count',
            )->selectSub(
                DB::table('consignment_pricing_versions as cpv')->select('cpv.total_amount')
                    ->whereColumn('cpv.consignment_id', 'c.consignment_id')
                    ->whereColumn('cpv.hq_id', 'c.hq_id')
                    ->orderByDesc('cpv.version_number')->limit(1),
                'payable_total_amount',
            )->selectSub(
                DB::table('consignment_pricing_versions as cpv')->select('cpv.currency')
                    ->whereColumn('cpv.consignment_id', 'c.consignment_id')
                    ->whereColumn('cpv.hq_id', 'c.hq_id')
                    ->orderByDesc('cpv.version_number')->limit(1),
                'payable_currency',
            );
    }

    /** @param array<string, mixed> $context
     *  @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function detail(array $row, array $context): array
    {
        $hqId = (string) $row['hq_id'];
        $id = (string) $row['consignment_id'];
        $offeringEvidence = $row['service_offering_version_id'] === null ? null : DB::table('service_offering_versions as ov')
            ->join('service_type_versions as stv', 'stv.service_type_version_id', '=', 'ov.service_type_version_id')
            ->join('shipping_method_versions as smv', 'smv.shipping_method_version_id', '=', 'ov.shipping_method_version_id')
            ->where('ov.service_offering_version_id', $row['service_offering_version_id'])
            ->where(fn (Builder $query) => $query->whereNull('ov.hq_id')->orWhere('ov.hq_id', $hqId))
            ->first(['ov.labels as offering_labels', 'stv.labels as service_type_labels', 'smv.labels as shipping_method_labels']);
        $parcels = in_array('parcel.view', $context['permissions'], true)
            ? DB::table('parcels')->where(['hq_id' => $hqId, 'consignment_id' => $id])
                ->orderBy('parcel_number')->get()->map(fn ($parcel): array => [
                    'parcel_id' => (string) $parcel->parcel_id,
                    'parcel_number' => (string) $parcel->parcel_number,
                    'current_status' => (string) $parcel->current_status,
                    'content_description' => $parcel->content_description,
                    'weight_kg' => $parcel->weight_kg === null ? null : (float) $parcel->weight_kg,
                    'width_cm' => $parcel->width_cm === null ? null : (float) $parcel->width_cm,
                    'length_cm' => $parcel->length_cm === null ? null : (float) $parcel->length_cm,
                    'height_cm' => $parcel->height_cm === null ? null : (float) $parcel->height_cm,
                    'current_node_id' => $parcel->current_node_id,
                    'current_custody_type' => (string) $parcel->current_custody_type,
                    'current_custodian_id' => $parcel->current_custodian_id,
                    'active_route_plan_id' => $parcel->active_route_plan_id,
                    'active_route_plan_leg_id' => $parcel->active_route_plan_leg_id,
                    'version' => (int) $parcel->version,
                    'created_at' => $this->time($parcel->created_at),
                ])->all()
            : [];
        $pricing = DB::table('consignment_pricing_versions')
            ->where(['hq_id' => $hqId, 'consignment_id' => $id])
            ->orderByDesc('version_number')->get()->map(function ($version) use ($hqId): array {
                $lines = DB::table('consignment_pricing_charge_lines')
                    ->where(['hq_id' => $hqId, 'pricing_version_id' => $version->pricing_version_id])
                    ->orderBy('line_number')->get()->map(fn ($line): array => [
                        'charge_code' => (string) $line->charge_code,
                        'title' => (string) $line->title,
                        'category' => $line->category,
                        'calculation_method' => $line->calculation_method,
                        'basis' => $line->basis,
                        'quantity' => $line->quantity === null ? null : (float) $line->quantity,
                        'unit_rate' => $line->unit_rate === null ? null : (float) $line->unit_rate,
                        'amount' => (int) $line->amount,
                        'explanation' => $line->explanation ? json_decode((string) $line->explanation, true) : null,
                    ])->all();

                return [
                    'pricing_version_id' => (string) $version->pricing_version_id,
                    'version_number' => (int) $version->version_number,
                    'provider_code' => (string) $version->provider_code,
                    'quote_id' => (string) $version->quote_id,
                    'quote_version' => (int) $version->quote_version,
                    'option_id' => (string) $version->option_id,
                    'external_method_code' => (string) $version->external_method_code,
                    'method_name' => (string) $version->method_name,
                    'external_price_list_code' => $version->external_price_list_code,
                    'zone' => $version->zone,
                    'currency' => (string) $version->currency,
                    'total_amount' => (int) $version->total_amount,
                    'min_ins' => $version->min_ins === null ? null : (int) $version->min_ins,
                    'delivery_windows' => json_decode((string) $version->delivery_windows, true) ?: [],
                    'accepted_at' => $this->time($version->accepted_at),
                    'charge_lines' => $lines,
                ];
            })->all();
        $statusTimeline = DB::table('consignment_status_events')
            ->where(['hq_id' => $hqId, 'consignment_id' => $id])
            ->orderBy('created_at')->orderByRaw('event_sequence IS NULL')->orderBy('event_sequence')->orderBy('status_event_id')->get()->map(fn ($event): array => [
                'status_event_id' => (string) $event->status_event_id,
                'event_sequence' => $event->event_sequence === null ? null : (int) $event->event_sequence,
                'parcel_id' => $event->parcel_id,
                'previous_status' => $event->previous_status,
                'new_status' => (string) $event->new_status,
                'aggregate_mode' => $event->aggregate_mode,
                'parcel_status_counts' => $event->parcel_status_counts === null
                    ? null
                    : json_decode((string) $event->parcel_status_counts, true),
                'initiator_id' => (string) $event->initiator_id,
                'node_id' => $event->node_id,
                'manifest_id' => $event->manifest_id,
                'correlation_id' => $event->correlation_id,
                'reason_code' => $event->reason_code,
                'note' => $event->note,
                'created_at' => $this->time($event->created_at),
            ])->all();
        $auditTimeline = in_array('audit.view', $context['permissions'], true)
            ? DB::table('audit_events')->where([
                'hq_id' => $hqId,
                'target_type' => 'CONSIGNMENT',
                'target_id' => $id,
            ])->orderBy('created_at')->get()->map(fn ($event): array => [
                'audit_id' => (string) $event->audit_id,
                'action_key' => (string) $event->action_key,
                'initiator_id' => $event->initiator_id,
                'safe_note' => $event->safe_note,
                'created_at' => $this->time($event->created_at),
            ])->all()
            : [];
        $custodyTimeline = DB::table('parcel_custody_events')->where(['hq_id' => $hqId, 'consignment_id' => $id])
            ->orderBy('created_at')->orderByRaw('event_sequence IS NULL')->orderBy('event_sequence')->orderBy('custody_event_id')->get()->map(fn ($event): array => [
                'custody_event_id' => (string) $event->custody_event_id,
                'event_sequence' => $event->event_sequence === null ? null : (int) $event->event_sequence,
                'parcel_id' => (string) $event->parcel_id,
                'from_node_id' => $event->from_node_id, 'to_node_id' => $event->to_node_id,
                'from_custody_type' => $event->from_custody_type, 'to_custody_type' => (string) $event->to_custody_type,
                'from_custodian_id' => $event->from_custodian_id, 'to_custodian_id' => $event->to_custodian_id,
                'command_name' => (string) $event->command_name, 'manifest_id' => $event->manifest_id,
                'route_plan_id' => $event->route_plan_id, 'route_plan_leg_id' => $event->route_plan_leg_id,
                'created_at' => $this->time($event->created_at),
            ])->all();
        $routePlan = DB::table('route_plans')->where(['hq_id' => $hqId, 'consignment_id' => $id])->orderByDesc('created_at')->first();
        $routeLegs = $routePlan === null ? [] : DB::table('route_plan_legs as l')
            ->join('nodes as o', 'o.node_id', '=', 'l.origin_node_id')->join('nodes as d', 'd.node_id', '=', 'l.destination_node_id')
            ->where('l.route_plan_id', $routePlan->route_plan_id)->orderBy('l.leg_order')->get(['l.*', 'o.node_code as origin_code', 'o.node_title as origin_title', 'd.node_code as destination_code', 'd.node_title as destination_title'])
            ->map(fn ($leg): array => [
                'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
                'source_route_definition_leg_id' => (string) $leg->source_route_definition_leg_id,
                'source_route_definition_version_leg_id' => (string) $leg->source_route_definition_version_leg_id,
                'leg_order' => (int) $leg->leg_order,
                'status' => (string) $leg->status,
                'origin_node' => ['node_id' => (string) $leg->origin_node_id, 'node_code' => (string) $leg->origin_code, 'node_title' => (string) $leg->origin_title],
                'destination_node' => ['node_id' => (string) $leg->destination_node_id, 'node_code' => (string) $leg->destination_code, 'node_title' => (string) $leg->destination_title],
                'routed_at' => $leg->routed_at === null ? null : $this->time($leg->routed_at),
                'received_at' => $leg->received_at === null ? null : $this->time($leg->received_at),
            ])->all();
        $location = $this->aggregateLocation($hqId, $id);
        $manifestRows = DB::table('manifests as m')
            ->join('manifest_parcels as mp', 'mp.manifest_id', '=', 'm.manifest_id')
            ->join('parcels as p', 'p.parcel_id', '=', 'mp.parcel_id')
            ->where(['m.hq_id' => $hqId, 'p.consignment_id' => $id])
            ->distinct()->orderBy('m.created_at')
            ->get([
                'm.manifest_id', 'm.manifest_number', 'm.manifest_status', 'm.manifest_type',
                'm.operational_context_type', 'm.context_key', 'm.node_id',
                'm.origin_node_id', 'm.destination_node_id', 'm.route_plan_id',
                'm.route_plan_leg_id', 'm.route_definition_version_id',
                'm.route_definition_version_leg_id', 'm.assigned_driver_id',
                'm.assigned_vehicle_id', 'm.state', 'm.operation_recorded_at',
                'm.closed_at', 'm.created_at',
            ]);
        $outcomesByManifest = $manifestRows->isEmpty()
            ? collect()
            : DB::table('manifest_parcels as mp')
                ->join('parcels as p', 'p.parcel_id', '=', 'mp.parcel_id')
                ->where('mp.hq_id', $hqId)
                ->where('p.consignment_id', $id)
                ->whereIn('mp.manifest_id', $manifestRows->pluck('manifest_id'))
                ->orderBy('mp.created_at')->get([
                    'mp.manifest_id', 'mp.manifest_parcel_id', 'mp.parcel_id',
                    'mp.manifest_parcel_status', 'mp.failure_code', 'mp.source_status',
                    'mp.route_plan_id', 'mp.route_plan_leg_id',
                    'mp.route_definition_version_id', 'mp.route_definition_version_leg_id',
                    'mp.assigned_driver_id', 'mp.assigned_vehicle_id',
                    'mp.evidence_recorded_at',
                ])->groupBy('manifest_id');
        $relatedManifests = $manifestRows->map(function ($manifest) use ($outcomesByManifest): array {
                $outcomes = $outcomesByManifest->get($manifest->manifest_id, collect())
                    ->map(fn ($outcome): array => (array) $outcome)->all();

                return [
                    ...(array) $manifest,
                    'parcel_outcomes' => $outcomes,
                ];
            })->all();
        $movementManifests = array_values(array_map(
            static function (array $manifest): array {
                $succeeded = collect($manifest['parcel_outcomes'])->first(
                    static fn (array $outcome): bool => $outcome['manifest_parcel_status'] === 'SUCCEEDED',
                );

                return [
                    ...$manifest,
                    'route_plan_id' => $manifest['route_plan_id'] ?? $succeeded['route_plan_id'],
                    'route_plan_leg_id' => $manifest['route_plan_leg_id'] ?? $succeeded['route_plan_leg_id'],
                    'route_definition_version_id' => $manifest['route_definition_version_id'] ?? $succeeded['route_definition_version_id'],
                    'route_definition_version_leg_id' => $manifest['route_definition_version_leg_id'] ?? $succeeded['route_definition_version_leg_id'],
                    'event_type' => $manifest['manifest_status'] === 'OS' ? 'DEPARTED' : 'RECEIVED',
                    'recorded_at' => $manifest['operation_recorded_at'] ?? $manifest['closed_at'],
                ];
            },
            array_filter($relatedManifests, static function (array $manifest): bool {
                $isMovement = in_array($manifest['manifest_status'], ['OS', 'CI'], true)
                    || ($manifest['manifest_status'] === 'IR'
                        && $manifest['operational_context_type'] === 'MOVEMENT_RECEPTION');
                $hasSucceededParcel = collect($manifest['parcel_outcomes'])->contains(
                    static fn (array $outcome): bool => $outcome['manifest_parcel_status'] === 'SUCCEEDED',
                );

                return $isMovement && $manifest['state'] === 'CLOSED' && $hasSucceededParcel;
            }),
        ));
        $routeLegOrder = collect($routeLegs)->mapWithKeys(
            static fn (array $leg): array => [$leg['route_plan_leg_id'] => $leg['leg_order']],
        );
        usort($movementManifests, static function (array $left, array $right) use ($routeLegOrder): int {
            $leftKey = [
                (int) $routeLegOrder->get($left['route_plan_leg_id'], PHP_INT_MAX),
                $left['event_type'] === 'DEPARTED' ? 0 : 1,
                (string) $left['recorded_at'],
                (string) $left['manifest_id'],
            ];
            $rightKey = [
                (int) $routeLegOrder->get($right['route_plan_leg_id'], PHP_INT_MAX),
                $right['event_type'] === 'DEPARTED' ? 0 : 1,
                (string) $right['recorded_at'],
                (string) $right['manifest_id'],
            ];

            return $leftKey <=> $rightKey;
        });
        $base = $this->listItem($row);
        $editable = in_array('consignment.edit', $context['permissions'], true)
            && in_array($row['current_status'], (array) config('chabok.consignment.editable_statuses'), true);

        return [
            ...$base,
            'sender' => $this->contactFromRow('sender', $row),
            'receiver' => $this->contactFromRow('receiver', $row),
            'service_type_id' => (string) $row['service_type_id'],
            'shipping_method_id' => (string) $row['shipping_method_id'],
            'service_offering_id' => $row['service_offering_id'] ? (string) $row['service_offering_id'] : null,
            'service_offering_version_id' => $row['service_offering_version_id'] ? (string) $row['service_offering_version_id'] : null,
            'service_offering_title' => $offeringEvidence === null ? null : $this->historicalLabel($offeringEvidence->offering_labels),
            'service_type_title' => $offeringEvidence === null ? null : $this->historicalLabel($offeringEvidence->service_type_labels),
            'shipping_method_title' => $offeringEvidence === null ? null : $this->historicalLabel($offeringEvidence->shipping_method_labels),
            'selected_service_option_versions' => $row['selected_service_option_versions'] ? json_decode((string) $row['selected_service_option_versions'], true) : [],
            'commitment_schedule_version_id' => $row['commitment_schedule_version_id'] ? (string) $row['commitment_schedule_version_id'] : null,
            'pickup_service_date' => $row['pickup_service_date'],
            'pickup_window_code' => $row['pickup_window_code'],
            'delivery_window_code' => $row['delivery_window_code'],
            'commitment_snapshot' => $row['commitment_snapshot'] ? json_decode((string) $row['commitment_snapshot'], true) : null,
            'commercial_pricing_state' => (string) $row['commercial_pricing_state'],
            'active_pricing_snapshot_id' => $row['active_pricing_snapshot_id'] ? (string) $row['active_pricing_snapshot_id'] : null,
            'pickup_commitment_at' => $row['pickup_commitment_at'] ? $this->time($row['pickup_commitment_at']) : null,
            'pickup_commitment_start_at' => $row['pickup_commitment_start_at'] ? $this->time($row['pickup_commitment_start_at']) : null,
            'pickup_commitment_end_at' => $row['pickup_commitment_end_at'] ? $this->time($row['pickup_commitment_end_at']) : null,
            'delivery_commitment_at' => $row['delivery_commitment_at'] ? $this->time($row['delivery_commitment_at']) : null,
            'delivery_commitment_start_at' => $row['delivery_commitment_start_at'] ? $this->time($row['delivery_commitment_start_at']) : null,
            'delivery_commitment_end_at' => $row['delivery_commitment_end_at'] ? $this->time($row['delivery_commitment_end_at']) : null,
            'weight_kg' => (float) $row['weight_kg'],
            'width_cm' => $row['width_cm'] === null ? null : (float) $row['width_cm'],
            'length_cm' => $row['length_cm'] === null ? null : (float) $row['length_cm'],
            'height_cm' => $row['height_cm'] === null ? null : (float) $row['height_cm'],
            'declared_value_amount' => (int) $row['declared_value_amount'],
            'insurance_enabled' => (bool) $row['insurance_enabled'],
            'insurance_value_amount' => $row['insurance_value_amount'] === null ? null : (int) $row['insurance_value_amount'],
            'cod_enabled' => (bool) $row['cod_enabled'],
            'cod_amount' => $row['cod_amount'] === null ? null : (int) $row['cod_amount'],
            'payer' => (string) $row['payer'],
            'payment_method' => (string) $row['payment_method'],
            'parcels' => $parcels,
            'accepted_pricing_versions' => $pricing,
            'status_timeline' => $statusTimeline,
            'audit_timeline' => $auditTimeline,
            'current_location' => $location,
            'journey' => [
                'route_plan' => $routePlan === null ? null : [
                    'route_plan_id' => (string) $routePlan->route_plan_id,
                    'route_definition_id' => (string) $routePlan->route_definition_id,
                    'route_definition_version_id' => (string) $routePlan->route_definition_version_id,
                    'status' => (string) $routePlan->status,
                    'version' => (int) $routePlan->version,
                ],
                'route_legs' => $routeLegs,
                'movement_manifests' => $movementManifests,
                'custody_timeline' => $custodyTimeline,
            ],
            'related_manifests' => $relatedManifests,
            'permitted_actions' => $editable ? ['EDIT'] : [],
        ];
    }

    private function applyNodeVisibility(Builder $query, string $nodeId): void
    {
        $query->where('c.pickup_node_id', $nodeId)->orWhere('c.delivery_node_id', $nodeId)
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('parcels as pv')->whereColumn('pv.consignment_id', 'c.consignment_id')->whereColumn('pv.hq_id', 'c.hq_id')->where('pv.current_node_id', $nodeId))
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('parcels as pt')->join('route_plan_legs as rpl', 'rpl.route_plan_leg_id', '=', 'pt.active_route_plan_leg_id')->whereColumn('pt.consignment_id', 'c.consignment_id')->whereColumn('pt.hq_id', 'c.hq_id')->where('rpl.destination_node_id', $nodeId)->where('rpl.status', 'IN_TRANSIT'))
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('pickup_tasks as ptask')->whereColumn('ptask.consignment_id', 'c.consignment_id')->whereColumn('ptask.hq_id', 'c.hq_id')->where('ptask.node_id', $nodeId)->whereIn('ptask.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))
            ->orWhereExists(fn ($q) => $q->selectRaw('1')->from('delivery_tasks as dtask')->whereColumn('dtask.consignment_id', 'c.consignment_id')->whereColumn('dtask.hq_id', 'c.hq_id')->where('dtask.node_id', $nodeId)->whereIn('dtask.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']));
    }

    /** @return array<string,mixed> */
    private function aggregateLocation(string $hqId, string $consignmentId): array
    {
        $parcels = DB::table('parcels')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->get();
        $nodes = $parcels->pluck('current_node_id')->unique();
        $custodies = $parcels->map(fn ($p): string => (string) $p->current_custody_type.'|'.(string) $p->current_custodian_id)->unique();
        if ($nodes->count() !== 1 || $custodies->count() !== 1) return ['state' => 'MIXED', 'node' => null, 'custody_type' => 'MIXED', 'custodian_id' => null];
        $nodeId = $nodes->first(); $first = $parcels->first();
        $node = $nodeId === null ? null : DB::table('nodes')->where(['hq_id' => $hqId, 'node_id' => $nodeId])->first();
        return ['state' => $nodeId === null ? 'IN_CUSTODY' : 'AT_NODE', 'node' => $node === null ? null : ['node_id' => (string) $node->node_id, 'node_code' => (string) $node->node_code, 'node_title' => (string) $node->node_title], 'custody_type' => (string) $first->current_custody_type, 'custodian_id' => $first->current_custodian_id];
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (($filters['search'] ?? null) !== null) {
            $term = '%'.addcslashes((string) $filters['search'], '%_\\').'%';
            $query->where(function (Builder $query) use ($term): void {
                $query->where('c.consignment_number', 'like', $term)
                    ->orWhere('c.receiver_contact_name', 'like', $term)
                    ->orWhere('c.receiver_mobile', 'like', $term)
                    ->orWhereExists(function (Builder $subquery) use ($term): void {
                        $subquery->selectRaw('1')->from('parcels as sp')
                            ->whereColumn('sp.consignment_id', 'c.consignment_id')
                            ->whereColumn('sp.hq_id', 'c.hq_id')
                            ->where('sp.parcel_number', 'like', $term);
                    });
            });
        }
        foreach ([
            'status' => 'current_status',
            'pickup_node_id' => 'pickup_node_id',
            'delivery_node_id' => 'delivery_node_id',
            'service_type_id' => 'service_type_id',
            'shipping_method_id' => 'shipping_method_id',
        ] as $input => $column) {
            if (($filters[$input] ?? null) !== null) {
                $query->where("c.{$column}", $filters[$input]);
            }
        }
        if (($filters['status_group'] ?? null) !== null) {
            $group = (string) $filters['status_group'];
            if ($group === 'UNASSIGNED') {
                $query->whereNull('c.pickup_man_id')->whereNull('c.delivery_man_id');
            } elseif ($group === 'ASSIGNED') {
                $query->where(function (Builder $query): void {
                    $query->whereNotNull('c.pickup_man_id')->orWhereNotNull('c.delivery_man_id');
                });
            } else {
                $query->whereIn('c.current_status', $this->statusGroup($group));
            }
        }
        if (($filters['created_from'] ?? null) !== null) {
            $query->where('c.created_at', '>=', $filters['created_from']);
        }
        if (($filters['created_to'] ?? null) !== null) {
            $query->where('c.created_at', '<=', $filters['created_to']);
        }
    }

    /** @return list<string> */
    private function statusGroup(string $group): array
    {
        return match ($group) {
            'NEW_ROUTED' => ['CFM', 'PD'],
            'IN_OPERATION' => ['PU', 'IR', 'ROU', 'OF', 'OS', 'OD'],
            'EXCEPTION' => ['NPU', 'NOK', 'RH', 'RCH'],
            'COMPLETED' => ['OK'],
            'CANCELLED' => ['RO', 'AA'],
            default => [],
        };
    }

    /** @return array{string, string} */
    private function sort(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');
        if (! in_array($field, ['created_at', 'updated_at', 'consignment_number', 'current_status'], true)) {
            $field = 'created_at';
            $direction = 'desc';
        }

        return [$field, $direction];
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    private function commercialColumns(array $input): array
    {
        return [
            'service_type_id' => $input['service_type_id'],
            'shipping_method_id' => $input['shipping_method_id'],
            'service_offering_id' => $input['service_offering_id'] ?? null,
            'service_offering_version_id' => $input['service_offering_version_id'] ?? null,
            'selected_service_option_versions' => isset($input['selected_option_version_ids'])
                ? json_encode(array_values((array) $input['selected_option_version_ids']), JSON_THROW_ON_ERROR)
                : null,
            'commitment_schedule_version_id' => $input['commitment_schedule_version_id'] ?? null,
            'pickup_service_date' => $input['pickup_service_date'] ?? null,
            'pickup_window_code' => $input['pickup_window_code'] ?? null,
            'delivery_window_code' => $input['delivery_window_code'] ?? null,
            'pickup_commitment_at' => $this->databaseTime($input['pickup_commitment_at'] ?? null),
            'pickup_commitment_start_at' => $this->databaseTime($input['pickup_commitment_start_at'] ?? null),
            'pickup_commitment_end_at' => $this->databaseTime($input['pickup_commitment_end_at'] ?? null),
            'delivery_commitment_at' => $this->databaseTime($input['delivery_commitment_at'] ?? null),
            'delivery_commitment_start_at' => $this->databaseTime($input['delivery_commitment_start_at'] ?? null),
            'delivery_commitment_end_at' => $this->databaseTime($input['delivery_commitment_end_at'] ?? null),
            'commitment_snapshot' => isset($input['commitment_snapshot']) ? json_encode($input['commitment_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'weight_kg' => $input['weight_kg'],
            'width_cm' => $input['width_cm'] ?? null,
            'length_cm' => $input['length_cm'] ?? null,
            'height_cm' => $input['height_cm'] ?? null,
            'declared_value_amount' => $input['declared_value_amount'],
            'insurance_enabled' => $input['insurance_enabled'],
            'insurance_value_amount' => $input['insurance_value_amount'] ?? null,
            'cod_enabled' => $input['cod_enabled'],
            'cod_amount' => $input['cod_amount'] ?? null,
            'payer' => $input['payer'],
            'payment_method' => $input['payment_method'],
        ];
    }

    /** @param array<string, mixed> $contact
     *  @return array<string, mixed>
     */
    private function contactColumns(string $prefix, array $contact): array
    {
        $result = [];
        foreach ([
            'contact_name', 'mobile', 'phone', 'address_text', 'country', 'state',
            'city', 'city_id', 'postal_code', 'latitude', 'longitude',
        ] as $field) {
            $result["{$prefix}_{$field}"] = $contact[$field] ?? null;
        }

        return $result;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function contactFromRow(string $prefix, array $row): array
    {
        $result = ['address_book_entry_id' => null];
        foreach ([
            'contact_name', 'mobile', 'phone', 'address_text', 'country', 'state',
            'city', 'city_id', 'postal_code', 'latitude', 'longitude',
        ] as $field) {
            $value = $row["{$prefix}_{$field}"];
            $result[$field] = in_array($field, ['latitude', 'longitude'], true) && $value !== null
                ? (float) $value
                : $value;
        }
        $result['city_reference'] = $this->geography->cityReference(
            isset($row["{$prefix}_city_id"]) ? (string) $row["{$prefix}_city_id"] : null,
        );

        return $result;
    }

    /** @param array<string, mixed> $parcel
     *  @param array<string, mixed> $aggregate
     *  @return array<string, mixed>
     */
    private function parcelPhysical(array $parcel, array $aggregate): array
    {
        $result = [];
        foreach (['weight_kg', 'width_cm', 'length_cm', 'height_cm'] as $field) {
            $result[$field] = $parcel[$field] ?? $aggregate[$field] ?? null;
        }

        return $result;
    }

    /** @param array<string, mixed> $accepted */
    private function persistPricing(
        string $hqId,
        string $consignmentId,
        int $version,
        string $actorId,
        array $accepted,
    ): string {
        $id = (string) Str::uuid();
        $acceptedAt = now();
        $snapshotId = ($accepted['provider_code'] ?? 'LEGACY_CORE') === 'INTERNAL'
            ? $this->persistInternalSnapshot($hqId, $consignmentId, $actorId, $version, $accepted)
            : null;
        DB::table('consignment_pricing_versions')->insert([
            'pricing_version_id' => $id,
            'pricing_snapshot_id' => $snapshotId,
            'service_offering_version_id' => $accepted['service_offering_version_id'] ?? null,
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'version_number' => $version,
            'provider_code' => $accepted['provider_code'] ?? 'LEGACY_CORE',
            'quote_id' => $accepted['quote_id'],
            'quote_version' => $accepted['quote_version'],
            'option_id' => $accepted['option_id'],
            'external_method_code' => $accepted['external_method_code'],
            'method_name' => $accepted['method_name'],
            'external_price_list_code' => $accepted['external_price_list_code'],
            'zone' => $accepted['zone'],
            'currency' => $accepted['currency'],
            'total_amount' => $accepted['total_amount'],
            'min_ins' => $accepted['min_ins'],
            'delivery_windows' => json_encode($accepted['delivery_windows'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'input_fingerprint' => $accepted['input_fingerprint'],
            'result_fingerprint' => $accepted['result_fingerprint'] ?? null,
            'provider_calculated_at' => CarbonImmutable::parse($accepted['provider_calculated_at']),
            'accepted_at' => $acceptedAt,
            'accepted_by' => $actorId,
        ]);
        foreach ($accepted['charge_lines'] as $index => $line) {
            DB::table('consignment_pricing_charge_lines')->insert([
                'pricing_charge_line_id' => (string) Str::uuid(),
                'hq_id' => $hqId,
                'pricing_version_id' => $id,
                'line_number' => $index + 1,
                'charge_code' => $line['charge_code'],
                'title' => $line['title'],
                'category' => $line['category'] ?? null,
                'calculation_method' => $line['calculation_method'] ?? null,
                'basis' => $line['basis'] ?? null,
                'quantity' => $line['quantity'] ?? null,
                'unit_rate' => $line['unit_rate'] ?? null,
                'amount' => $line['amount'],
                'explanation' => isset($line['explanation']) ? json_encode($line['explanation'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            ]);
        }
        DB::table('consignments')->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->update([
            'service_offering_id' => $accepted['service_offering_id'] ?? DB::raw('service_offering_id'),
            'service_offering_version_id' => $accepted['service_offering_version_id'] ?? DB::raw('service_offering_version_id'),
            'commercial_pricing_state' => 'LOCKED',
            'active_pricing_snapshot_id' => $snapshotId,
            'pricing_relevant_fingerprint' => $accepted['input_fingerprint'],
        ]);

        return $id;
    }

    /** @param array<string, mixed> $accepted */
    private function persistInternalSnapshot(
        string $hqId,
        string $consignmentId,
        string $actorId,
        int $version,
        array $accepted,
    ): string {
        $quoteId = (string) $accepted['internal_quote_id'];
        $quote = DB::table('pricing_quotes')->where(['quote_id' => $quoteId, 'hq_id' => $hqId])->lockForUpdate()->first();
        if ($quote === null || (string) $quote->status !== 'OFFERED') {
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'The internal pricing quote is unavailable.');
        }
        if (CarbonImmutable::parse((string) $quote->expires_at)->isPast()) {
            throw new ApiException(ApiErrorCode::PricingQuoteExpired, 422, 'The internal pricing quote has expired.');
        }
        $snapshotId = (string) Str::uuid();
        $now = now();
        DB::table('pricing_snapshots')->insert([
            'pricing_snapshot_id' => $snapshotId,
            'hq_id' => $hqId,
            'quote_id' => $quoteId,
            'object_type' => 'CONSIGNMENT',
            'object_id' => $consignmentId,
            'purpose' => 'SALES',
            'currency' => $quote->currency,
            'subtotal_amount' => $quote->subtotal_amount,
            'discount_amount' => $quote->discount_amount,
            'tax_amount' => $quote->tax_amount,
            'total_amount' => $quote->total_amount,
            'input_fingerprint' => $quote->input_fingerprint,
            'result_fingerprint' => $quote->result_fingerprint,
            'acceptance_idempotency_key' => "consignment:{$consignmentId}:{$version}",
            'accepted_by' => $actorId,
            'accepted_at' => $now,
        ]);
        foreach (DB::table('pricing_quote_lines')->where('quote_id', $quoteId)->orderBy('line_number')->get() as $line) {
            $copy = (array) $line;
            unset($copy['quote_line_id'], $copy['quote_id']);
            $copy['charge_line_id'] = (string) Str::uuid();
            $copy['pricing_snapshot_id'] = $snapshotId;
            DB::table('pricing_charge_lines')->insert($copy);
        }
        DB::table('pricing_quotes')->where('quote_id', $quoteId)->update(['status' => 'ACCEPTED', 'accepted_at' => $now, 'updated_at' => $now]);

        return $snapshotId;
    }

    private function insertStatusEvent(
        string $hqId,
        string $consignmentId,
        ?string $parcelId,
        string $actorId,
        string $nodeId,
        ?string $previous,
        string $new,
        string $reason,
        string $correlationId,
    ): void {
        DB::table('consignment_status_events')->insert([
            'status_event_id' => (string) Str::uuid(),
            'event_sequence' => $this->nextSequence('consignment_status_events', $consignmentId),
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'parcel_id' => $parcelId,
            'previous_status' => $previous,
            'new_status' => $new,
            'initiator_id' => $actorId,
            'node_id' => $nodeId,
            'correlation_id' => $correlationId,
            'reason_code' => $reason,
            'created_at' => now(),
        ]);
    }

    private function historicalLabel(mixed $labels): ?string
    {
        $decoded = is_string($labels) ? json_decode($labels, true) : (array) $labels;
        foreach (['fa', 'en'] as $locale) {
            $label = trim((string) ($decoded[$locale] ?? ''));
            if ($label !== '') return $label;
        }
        return null;
    }

    private function nextSequence(string $table, string $consignmentId): int
    {
        return ((int) DB::table($table)->where('consignment_id', $consignmentId)->max('event_sequence')) + 1;
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    private function draftFromRow(array $row): array
    {
        return [
            'sender' => $this->contactFromRow('sender', $row),
            'receiver' => $this->contactFromRow('receiver', $row),
            'service_type_id' => $row['service_type_id'],
            'shipping_method_id' => $row['shipping_method_id'],
            'service_offering_id' => $row['service_offering_id'],
            'service_offering_version_id' => $row['service_offering_version_id'],
            'selected_option_version_ids' => $row['selected_service_option_versions'] ? json_decode((string) $row['selected_service_option_versions'], true) : [],
            'commitment_schedule_version_id' => $row['commitment_schedule_version_id'],
            'pickup_service_date' => $row['pickup_service_date'],
            'pickup_window_code' => $row['pickup_window_code'],
            'delivery_window_code' => $row['delivery_window_code'],
            'pickup_commitment_at' => $row['pickup_commitment_at'] ? $this->time($row['pickup_commitment_at']) : null,
            'pickup_commitment_start_at' => $row['pickup_commitment_start_at'] ? $this->time($row['pickup_commitment_start_at']) : null,
            'pickup_commitment_end_at' => $row['pickup_commitment_end_at'] ? $this->time($row['pickup_commitment_end_at']) : null,
            'delivery_commitment_at' => $row['delivery_commitment_at'] ? $this->time($row['delivery_commitment_at']) : null,
            'delivery_commitment_start_at' => $row['delivery_commitment_start_at'] ? $this->time($row['delivery_commitment_start_at']) : null,
            'delivery_commitment_end_at' => $row['delivery_commitment_end_at'] ? $this->time($row['delivery_commitment_end_at']) : null,
            'commitment_snapshot' => $row['commitment_snapshot'] ? json_decode((string) $row['commitment_snapshot'], true) : null,
            'weight_kg' => (float) $row['weight_kg'],
            'width_cm' => $row['width_cm'] === null ? null : (float) $row['width_cm'],
            'length_cm' => $row['length_cm'] === null ? null : (float) $row['length_cm'],
            'height_cm' => $row['height_cm'] === null ? null : (float) $row['height_cm'],
            'declared_value_amount' => (int) $row['declared_value_amount'],
            'insurance_enabled' => (bool) $row['insurance_enabled'],
            'insurance_value_amount' => $row['insurance_value_amount'] === null ? null : (int) $row['insurance_value_amount'],
            'cod_enabled' => (bool) $row['cod_enabled'],
            'cod_amount' => $row['cod_amount'] === null ? null : (int) $row['cod_amount'],
            'payer' => $row['payer'],
            'payment_method' => $row['payment_method'],
            'parcels' => DB::table('parcels')->where([
                'hq_id' => $row['hq_id'],
                'consignment_id' => $row['consignment_id'],
            ])->orderBy('parcel_number')->get()->map(fn ($parcel): array => [
                'weight_kg' => $parcel->weight_kg === null ? null : (float) $parcel->weight_kg,
                'content_description' => $parcel->content_description,
                'width_cm' => $parcel->width_cm === null ? null : (float) $parcel->width_cm,
                'length_cm' => $parcel->length_cm === null ? null : (float) $parcel->length_cm,
                'height_cm' => $parcel->height_cm === null ? null : (float) $parcel->height_cm,
            ])->all(),
        ];
    }

    /** @param array<string,mixed>|null $commitment @param array<string,mixed> $input @return array<string,mixed> */
    private function withAcceptedCommitment(array $input, ?array $commitment): array
    {
        if ($commitment === null) return $input;
        $pickup = is_array($commitment['pickup'] ?? null) ? $commitment['pickup'] : [];
        $delivery = is_array($commitment['delivery'] ?? null) ? $commitment['delivery'] : [];
        $selectedDelivery = is_array($delivery['selected'] ?? null) ? $delivery['selected'] : [];
        $computedDelivery = $delivery['computed_at'] ?? null;
        $input['commitment_schedule_version_id'] = $commitment['schedule_version_id'] ?? null;
        $input['pickup_service_date'] = $pickup['service_date'] ?? ($input['pickup_service_date'] ?? null);
        $input['pickup_window_code'] = $pickup['window_code'] ?? ($input['pickup_window_code'] ?? null);
        $input['delivery_window_code'] = $selectedDelivery['window_code'] ?? ($input['delivery_window_code'] ?? null);
        $input['pickup_commitment_start_at'] = $pickup['starts_at'] ?? null;
        $input['pickup_commitment_end_at'] = $pickup['ends_at'] ?? null;
        $input['pickup_commitment_at'] = $pickup['ends_at'] ?? null;
        $input['delivery_commitment_start_at'] = $selectedDelivery['starts_at'] ?? $computedDelivery;
        $input['delivery_commitment_end_at'] = $selectedDelivery['ends_at'] ?? $computedDelivery;
        $input['delivery_commitment_at'] = $selectedDelivery['ends_at'] ?? $computedDelivery;
        $input['commitment_snapshot'] = $commitment;
        return $input;
    }

    private function time(mixed $value): string
    {
        return CarbonImmutable::parse((string) $value, 'UTC')->utc()->toISOString();
    }

    /** @param array<string,mixed> $row @return array{status:string,mode:string,parcel_counts:array<string,int>,parcel_total:int,target_count:int} */
    private function aggregateResource(array $row): array
    {
        $counts = json_decode((string) ($row['parcel_status_counts'] ?? '{}'), true) ?: [];
        $counts = array_map(static fn ($count): int => (int) $count, $counts);
        ksort($counts);
        $status = (string) $row['current_status'];

        return [
            'status' => $status,
            'mode' => (string) ($row['aggregate_mode'] ?? 'FULL'),
            'parcel_counts' => $counts,
            'parcel_total' => array_sum($counts),
            'target_count' => $counts[$status] ?? 0,
        ];
    }

    private function databaseTime(mixed $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->utc();
    }
}
