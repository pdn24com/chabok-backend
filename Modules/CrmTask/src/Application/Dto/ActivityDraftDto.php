<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

use DateTimeImmutable;
use Modules\CrmTask\Domain\Enums\ActivityChannel;
use Modules\CrmTask\Domain\Enums\ActivityDirection;
use Modules\CrmTask\Domain\Enums\ActivityType;
use Modules\CrmTask\Domain\Enums\CallOutcome;
use Modules\CrmTask\Domain\Enums\MeetingMode;

/**
 * One recorded interaction. Only the type, the time and the customer are common to all of them; the
 * rest belongs to a single kind of interaction and stays null for the others.
 */
final readonly class ActivityDraftDto
{
    public function __construct(
        public ActivityType $type,
        public DateTimeImmutable $occurredAt,
        public ?string $body = null,
        public ?string $result = null,
        public ?ActivityDirection $direction = null,
        /** The PERSON actually spoken to, which need not be the customer the task is filed against. */
        public ?string $contactCustomerId = null,
        /** Snapshot of the number, address or handle used, exactly as it stood at the time. */
        public ?string $contactValue = null,
        public ?ActivityChannel $channel = null,
        public ?int $durationMinutes = null,
        public ?CallOutcome $callOutcome = null,
        public ?MeetingMode $meetingMode = null,
        public ?string $location = null,
        public ?string $meetingUrl = null,
        public ?string $documentVersionId = null,
        /** The colleagues who sat in a meeting; empty for every other kind of interaction. @var list<ActivityParticipantDto> */
        public array $participants = [],
    ) {}
}
