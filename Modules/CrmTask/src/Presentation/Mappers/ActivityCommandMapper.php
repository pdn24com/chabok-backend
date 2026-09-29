<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Mappers;

use DateTimeImmutable;
use DateTimeZone;
use Modules\CrmTask\Application\Dto\ActivityDraftDto;
use Modules\CrmTask\Application\Dto\ActivityFilingDto;
use Modules\CrmTask\Application\Dto\ActivityParticipantDto;
use Modules\CrmTask\Application\UseCases\RecordActivity\RecordActivityCommand;
use Modules\CrmTask\Domain\Enums\ActivityChannel;
use Modules\CrmTask\Domain\Enums\ActivityDirection;
use Modules\CrmTask\Domain\Enums\ActivityType;
use Modules\CrmTask\Domain\Enums\CallOutcome;
use Modules\CrmTask\Domain\Enums\MeetingMode;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class ActivityCommandMapper
{
    public static function record(AuthenticatedPrincipal $actor, array $input): RecordActivityCommand
    {
        return new RecordActivityCommand(
            $actor,
            new ActivityDraftDto(
                type: ActivityType::from($input['type']),
                // Wall-clock input is stored in UTC; the offset the client sent decides which instant that is.
                occurredAt: (new DateTimeImmutable($input['occurred_at']))->setTimezone(new DateTimeZone('UTC')),
                body: $input['body'] ?? null,
                result: $input['result'] ?? null,
                direction: isset($input['direction']) ? ActivityDirection::from($input['direction']) : null,
                contactCustomerId: isset($input['contact_customer_id']) ? (string) $input['contact_customer_id'] : null,
                contactValue: $input['contact_value'] ?? null,
                channel: isset($input['channel']) ? ActivityChannel::from($input['channel']) : null,
                durationMinutes: isset($input['duration_minutes']) ? (int) $input['duration_minutes'] : null,
                callOutcome: isset($input['call_outcome']) ? CallOutcome::from($input['call_outcome']) : null,
                meetingMode: isset($input['meeting_mode']) ? MeetingMode::from($input['meeting_mode']) : null,
                location: $input['location'] ?? null,
                meetingUrl: $input['meeting_url'] ?? null,
                documentVersionId: isset($input['document_version_id']) ? (string) $input['document_version_id'] : null,
                participants: array_map(
                    static fn (array $participant): ActivityParticipantDto => new ActivityParticipantDto(
                        (string) $participant['user_id'],
                        (int) $participant['minutes'],
                    ),
                    array_values($input['participants'] ?? []),
                ),
            ),
            new ActivityFilingDto(
                customerId: isset($input['customer_id']) ? (string) $input['customer_id'] : null,
                opportunityId: isset($input['opportunity_id']) ? (string) $input['opportunity_id'] : null,
                taskId: isset($input['task_id']) ? (string) $input['task_id'] : null,
            ),
        );
    }
}
