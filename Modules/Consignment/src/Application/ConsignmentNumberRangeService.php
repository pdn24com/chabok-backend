<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Consignment\Domain\ConsignmentNumberRangeDefinition;
use Modules\Consignment\Domain\DecimalString;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConsignmentNumberRangeService
{
    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private ConsignmentNumberRangeDefinition $definition,
    ) {}

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function validate(AuthenticatedPrincipal $actor, array $input): array
    {
        $this->access($actor, 'consignment.number_range.manage');
        $preview = $this->definition->validate($input);
        $overlap = $this->overlaps($preview);
        return [...$preview, 'overlaps_existing_range' => $overlap, 'validation_result' => $overlap ? 'OVERLAP' : 'VALID'];
    }

    /** @param array<string,mixed> $filters */
    public function ranges(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $hqId = $this->access($actor, 'consignment.number_range.view');
        $query = DB::table('consignment_number_ranges')->where('hq_id', $hqId);
        if (($filters['status'] ?? null) !== null) $query->where('status', $filters['status']);
        return $query->orderByDesc('created_at')->orderBy('range_id')->paginate(
            (int) ($filters['page_size'] ?? 25), ['*'], 'page', (int) ($filters['page'] ?? 1),
        );
    }

    /** @return array<string,mixed> */
    public function range(AuthenticatedPrincipal $actor, string $rangeId): array
    {
        $hqId = $this->access($actor, 'consignment.number_range.view');
        $row = DB::table('consignment_number_ranges')->where(['hq_id' => $hqId, 'range_id' => $rangeId])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        return $this->resource($row);
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->access($actor, 'consignment.number_range.manage');
        $rangeId = $this->transactions->run(function () use ($actor, $hqId, $input, $correlationId): string {
            DB::table('consignment_number_range_registry')->where('registry_key', 'GLOBAL')->lockForUpdate()->first();
            $preview = $this->definition->validate($input);
            if ($this->overlaps($preview)) {
                throw new ApiException(ApiErrorCode::ConsignmentNumberRangeOverlap, 409, 'The requested number interval conflicts with an existing range.');
            }
            $rangeId = (string) Str::uuid();
            DB::table('consignment_number_ranges')->insert([
                'range_id' => $rangeId, 'hq_id' => $hqId, 'title' => trim((string) $input['title']),
                'numeric_prefix' => $preview['numeric_prefix'], 'total_length' => $preview['total_length'],
                'serial_width' => $preview['serial_width'], 'serial_start' => $preview['serial_start'],
                'serial_end' => $preview['serial_end'], 'next_serial' => $preview['serial_start'],
                'first_number' => $preview['first_number'], 'last_number' => $preview['last_number'],
                'status' => 'AVAILABLE', 'created_by' => $actor->userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->write($hqId, $actor->userId, 'CONSIGNMENT_NUMBER_RANGE_CREATED', 'CONSIGNMENT_NUMBER_RANGE', $rangeId, $correlationId, after: ['status' => 'AVAILABLE', 'first_number' => $preview['first_number'], 'last_number' => $preview['last_number']], sourceClient: 'BRANCH_PANEL');
            $this->outbox->write($hqId, 'CONSIGNMENT_NUMBER_RANGE', $rangeId, 'consignment.number-range.created', $correlationId, ['range_id' => $rangeId, 'status' => 'AVAILABLE']);
            return $rangeId;
        });
        return $this->range($actor, $rangeId);
    }

    /** @return array<string,mixed> */
    public function disable(AuthenticatedPrincipal $actor, string $rangeId, string $correlationId): array
    {
        $hqId = $this->access($actor, 'consignment.number_range.manage');
        $this->transactions->run(function () use ($actor, $hqId, $rangeId, $correlationId): void {
            $row = DB::table('consignment_number_ranges')->where(['hq_id' => $hqId, 'range_id' => $rangeId])->lockForUpdate()->first();
            if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            if ($row->status !== 'AVAILABLE') throw new ApiException(ApiErrorCode::ConsignmentNumberRangeUnavailable, 422, 'Only an available range can be disabled.');
            DB::table('consignment_number_ranges')->where(['hq_id' => $hqId, 'range_id' => $rangeId])->update([
                'status' => 'DISABLED', 'disabled_by' => $actor->userId, 'disabled_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->write($hqId, $actor->userId, 'CONSIGNMENT_NUMBER_RANGE_DISABLED', 'CONSIGNMENT_NUMBER_RANGE', $rangeId, $correlationId, before: ['status' => 'AVAILABLE'], after: ['status' => 'DISABLED'], sourceClient: 'BRANCH_PANEL');
            $this->outbox->write($hqId, 'CONSIGNMENT_NUMBER_RANGE', $rangeId, 'consignment.number-range.disabled', $correlationId, ['range_id' => $rangeId, 'status' => 'DISABLED']);
        });
        return $this->range($actor, $rangeId);
    }

    /** @param array<string,mixed> $filters */
    public function allocations(AuthenticatedPrincipal $actor, string $rangeId, array $filters): LengthAwarePaginator
    {
        $hqId = $this->access($actor, 'consignment.number_range.view');
        if (! DB::table('consignment_number_ranges')->where(['hq_id' => $hqId, 'range_id' => $rangeId])->exists()) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return DB::table('consignment_number_allocations')->where(['hq_id' => $hqId, 'range_id' => $rangeId])
            ->orderByDesc('allocated_at')->orderBy('allocation_id')
            ->paginate((int) ($filters['page_size'] ?? 25), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    /** @return array<string,mixed> */
    public function allocationResource(object $row): array
    {
        return [
            'allocation_id' => (string) $row->allocation_id, 'range_id' => (string) $row->range_id,
            'consignment_id' => (string) $row->consignment_id, 'consignment_number' => (string) $row->consignment_number,
            'allocated_by' => (string) $row->allocated_by, 'allocated_at' => (string) $row->allocated_at,
            'correlation_id' => (string) $row->correlation_id,
        ];
    }

    /** @return array<string,mixed> */
    public function resource(object $row): array
    {
        $capacity = DecimalString::inclusiveCount((string) $row->serial_start, (string) $row->serial_end);
        $allocated = $row->next_serial === null ? $capacity : DecimalString::subtract((string) $row->next_serial, (string) $row->serial_start);
        return [
            'range_id' => (string) $row->range_id, 'title' => (string) $row->title,
            'numeric_prefix' => (string) $row->numeric_prefix, 'total_length' => (int) $row->total_length,
            'serial_width' => (int) $row->serial_width, 'serial_start' => (string) $row->serial_start,
            'serial_end' => (string) $row->serial_end, 'first_number' => (string) $row->first_number,
            'last_number' => (string) $row->last_number,
            'next_number' => $row->next_serial === null ? null : (string) $row->numeric_prefix.$row->next_serial,
            'total_capacity' => $capacity, 'allocated_count' => $allocated,
            'remaining_count' => DecimalString::subtract($capacity, $allocated), 'status' => (string) $row->status,
            'created_by' => (string) $row->created_by, 'created_at' => (string) $row->created_at,
            'updated_at' => (string) $row->updated_at,
            'disabled_by' => $row->disabled_by === null ? null : (string) $row->disabled_by,
            'disabled_at' => $row->disabled_at === null ? null : (string) $row->disabled_at,
            'exhausted_at' => $row->exhausted_at === null ? null : (string) $row->exhausted_at,
        ];
    }

    /** @param array<string,mixed> $preview */
    private function overlaps(array $preview): bool
    {
        return DB::table('consignment_number_ranges')->where('total_length', $preview['total_length'])
            ->where('first_number', '<=', $preview['last_number'])->where('last_number', '>=', $preview['first_number'])->exists();
    }

    private function access(AuthenticatedPrincipal $actor, string $permission): string
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        $entitled = collect($context['module_entitlements'] ?? [])->contains(fn (array $item): bool => ($item['module_code'] ?? null) === 'Consignment' && ($item['status'] ?? null) === 'ENABLED');
        if (! $entitled) throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        if (! in_array($permission, $context['permissions'] ?? [], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        return (string) $actor->hqId;
    }
}
