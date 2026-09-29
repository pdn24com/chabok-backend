<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\Enums;

/**
 * The six drawers the customer history page is split into. TICKETS and OPERATIONS answer empty on purpose:
 * no ticket table exists in this model, and a consignment is read in Operations filtered by customer, so
 * neither is fabricated here.
 */
enum CustomerHistoryCategory: string
{
    /** Why a category answers empty, so the page can say it instead of showing a bare zero. */
    public function unavailableReason(): ?string
    {
        return match ($this) {
            self::TICKETS => 'customer.history_tickets_have_no_source',
            self::OPERATIONS => 'customer.history_operations_live_in_operations',
            default => null,
        };
    }
    case WORK = 'work';
    case FINANCE = 'finance';
    case CORRESPONDENCE = 'correspondence';
    case TICKETS = 'tickets';
    case OPERATIONS = 'operations';
    case CHANGES = 'changes';
}
