<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Services;

use BackedEnum;
use Modules\Audit\Application\Repositories\AuditEventRepositoryInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;
use Modules\CrmTask\Application\Repositories\ActivityRepositoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\Customer\Application\Contracts\CustomerHistoryReaderInterface;
use Modules\Customer\Application\Dto\CustomerHistoryEntryDto;
use Modules\Customer\Application\Ports\CustomerFinanceHistoryInterface;
use Modules\Customer\Domain\Enums\CustomerHistoryCategory;

/**
 * Gathers the customer history from the modules that own each table and normalises it into one shape.
 *
 * Tasks, interactions, sales documents and audit entries are read straight from their owning module.
 * Finance arrives through a Customer port instead, because CrmFinance already reads Customer and
 * pointing the dependency back would close a cycle.
 */
final readonly class CustomerHistoryReader implements CustomerHistoryReaderInterface
{
    /** The interaction types that count as work versus the ones that count as correspondence. */
    private const WORK_ACTIVITY_TYPES = ['CALL', 'MEETING'];

    private const CORRESPONDENCE_ACTIVITY_TYPES = ['MESSAGE', 'DOCUMENT_SENT', 'NOTE', 'REFERRAL'];

    public function __construct(
        private TaskRepositoryInterface $tasks,
        private ActivityRepositoryInterface $activities,
        private SalesDocumentRepositoryInterface $salesDocuments,
        private CustomerFinanceHistoryInterface $finance,
        private AuditEventRepositoryInterface $auditEvents,
    ) {}

    public function entries(string $hqId, string $customerId, CustomerHistoryCategory $category): array
    {
        $rows = match ($category) {
            CustomerHistoryCategory::WORK => [
                ...$this->tasks($hqId, $customerId),
                ...$this->activities($hqId, $customerId, self::WORK_ACTIVITY_TYPES, $category),
            ],
            CustomerHistoryCategory::FINANCE => [
                ...$this->salesDocuments($hqId, $customerId),
                ...$this->finance->historyForCustomer($hqId, $customerId),
            ],
            CustomerHistoryCategory::CORRESPONDENCE => $this->activities($hqId, $customerId, self::CORRESPONDENCE_ACTIVITY_TYPES, $category),
            CustomerHistoryCategory::CHANGES => $this->changes($hqId, $customerId),
            // No ticket table exists in this model, and a consignment is read in Operations filtered by
            // customer. Both answer empty rather than being invented here.
            CustomerHistoryCategory::TICKETS, CustomerHistoryCategory::OPERATIONS => [],
        };

        // A category can draw on two tables, so the merged rows are ordered here. An undated row sorts
        // last rather than being dropped.
        usort($rows, static fn (CustomerHistoryEntryDto $left, CustomerHistoryEntryDto $right): int => ($right->occurredAt?->getTimestamp() ?? 0) <=> ($left->occurredAt?->getTimestamp() ?? 0));

        return $rows;
    }

    /** @return list<CustomerHistoryEntryDto> */
    private function tasks(string $hqId, string $customerId): array
    {
        $rows = [];
        foreach ($this->tasks->historyForCustomer($hqId, $customerId) as $task) {
            $rows[] = new CustomerHistoryEntryDto(
                category: CustomerHistoryCategory::WORK,
                entryType: 'TASK',
                entryId: $task->task_id,
                title: $task->title,
                occurredAt: $task->completed_at ?? $task->created_at,
                kind: 'TASK',
                status: $task->status instanceof BackedEnum ? $task->status->value : $task->status,
            );
        }

        return $rows;
    }

    /**
     * @param  list<string>  $types
     * @return list<CustomerHistoryEntryDto>
     */
    private function activities(string $hqId, string $customerId, array $types, CustomerHistoryCategory $category): array
    {
        $rows = [];
        foreach ($this->activities->historyForCustomer($hqId, $customerId, $types) as $activity) {
            $type = $activity->type instanceof BackedEnum ? $activity->type->value : (string) $activity->type;
            $rows[] = new CustomerHistoryEntryDto(
                category: $category,
                entryType: 'ACTIVITY',
                entryId: $activity->activity_id,
                // An interaction has no title of its own, so the outcome names it and the note stands in
                // for a note-only entry.
                title: $activity->result ?? $activity->body ?? $type,
                occurredAt: $activity->occurred_at,
                kind: $type,
                status: $activity->direction instanceof BackedEnum ? $activity->direction->value : $activity->direction,
            );
        }

        return $rows;
    }

    /** @return list<CustomerHistoryEntryDto> */
    private function salesDocuments(string $hqId, string $customerId): array
    {
        $rows = [];
        foreach ($this->salesDocuments->historyForCustomer($hqId, $customerId) as $document) {
            $version = $document->currentVersion;
            $rows[] = new CustomerHistoryEntryDto(
                category: CustomerHistoryCategory::FINANCE,
                entryType: 'SALES_DOCUMENT',
                entryId: $document->sales_document_id,
                title: $document->document_no,
                // A document is dated by the revision it currently shows, and by its creation while it
                // has none.
                occurredAt: $version?->issued_at ?? $document->created_at,
                kind: $document->document_type instanceof BackedEnum ? $document->document_type->value : $document->document_type,
                status: $version?->status instanceof BackedEnum ? $version->status->value : $version?->status,
                amount: $version?->total,
            );
        }

        return $rows;
    }

    /** @return list<CustomerHistoryEntryDto> */
    private function changes(string $hqId, string $customerId): array
    {
        $rows = [];
        foreach ($this->auditEvents->historyForResource($hqId, 'crm_customers', $customerId) as $event) {
            $rows[] = new CustomerHistoryEntryDto(
                category: CustomerHistoryCategory::CHANGES,
                entryType: 'AUDIT',
                entryId: $event->audit_id,
                title: $event->action_key,
                occurredAt: $event->created_at,
                kind: 'CHANGE',
                status: $event->safe_note,
                actor: $event->initiator?->display_name,
            );
        }

        return $rows;
    }
}
