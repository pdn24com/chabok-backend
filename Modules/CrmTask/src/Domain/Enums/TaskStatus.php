<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum TaskStatus: string
{
    /**
     * A task stays open until it is completed or cancelled; waiting on the customer is still work in hand.
     *
     * @return list<string>
     */
    public static function closedValues(): array
    {
        return [self::COMPLETED->value, self::CANCELLED->value];
    }
    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case WAITING_CUSTOMER = 'WAITING_CUSTOMER';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}
