<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Presentation\Mappers;

use DateTimeImmutable;
use Modules\CrmOpportunitie\Application\Dto\OpportunityBoardFiltersDto;
use Modules\CrmOpportunitie\Application\Dto\OpportunityDraftDto;
use Modules\CrmOpportunitie\Application\Dto\SalesFunnelFiltersDto;
use Modules\CrmOpportunitie\Application\Dto\StepTransitionDto;
use Modules\CrmOpportunitie\Application\UseCases\CreateOpportunity\CreateOpportunityCommand;
use Modules\CrmOpportunitie\Application\UseCases\ListOpportunityBoard\ListOpportunityBoardCommand;
use Modules\CrmOpportunitie\Application\UseCases\ListOpportunityEvents\ListOpportunityEventsCommand;
use Modules\CrmOpportunitie\Application\UseCases\ListSalesFunnels\ListSalesFunnelsCommand;
use Modules\CrmOpportunitie\Application\UseCases\TransitionOpportunityStep\TransitionOpportunityStepCommand;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class OpportunityCommandMapper
{
    public static function funnelListing(AuthenticatedPrincipal $actor, array $input): ListSalesFunnelsCommand
    {
        return new ListSalesFunnelsCommand($actor, new SalesFunnelFiltersDto(
            // Absent means both the active and the retired funnels, which is not the same as active=false.
            active: array_key_exists('active', $input) ? (bool) $input['active'] : null,
        ));
    }

    public static function board(AuthenticatedPrincipal $actor, array $input): ListOpportunityBoardCommand
    {
        return new ListOpportunityBoardCommand($actor, new OpportunityBoardFiltersDto(
            funnelId: (string) $input['funnel_id'],
            customerId: isset($input['customer_id']) ? (string) $input['customer_id'] : null,
            assigneeId: isset($input['assignee_id']) ? (string) $input['assignee_id'] : null,
        ));
    }

    public static function draft(AuthenticatedPrincipal $actor, array $input): CreateOpportunityCommand
    {
        return new CreateOpportunityCommand($actor, new OpportunityDraftDto(
            customerId: (string) $input['customer_id'],
            funnelId: (string) $input['funnel_id'],
            title: $input['title'],
            assigneeId: (string) $input['assignee_id'],
            amount: isset($input['amount']) ? (int) $input['amount'] : null,
            probability: self::decimal($input['probability'] ?? null),
            expectedClose: self::instant($input['expected_close'] ?? null),
        ));
    }

    public static function transition(AuthenticatedPrincipal $actor, string $opportunityId, array $input): TransitionOpportunityStepCommand
    {
        return new TransitionOpportunityStepCommand($actor, $opportunityId, new StepTransitionDto(
            fromStepId: (string) $input['from_step_id'],
            toStepId: (string) $input['to_step_id'],
            reason: $input['reason'] ?? null,
            occurredAt: self::instant($input['occurred_at'] ?? null),
            evidenceActivityId: isset($input['evidence_activity_id']) ? (string) $input['evidence_activity_id'] : null,
            closeReason: $input['close_reason'] ?? null,
        ));
    }

    public static function events(AuthenticatedPrincipal $actor, string $opportunityId): ListOpportunityEventsCommand
    {
        return new ListOpportunityEventsCommand($actor, $opportunityId);
    }

    /** A unix timestamp in seconds; the epoch spelling makes the instant UTC whatever the server clock is. */
    private static function instant(int|string|null $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable('@'.$value);
    }

    /** A percentage keeps the decimal spelling it arrived in, so the stored precision is never rounded. */
    private static function decimal(float|int|string|null $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
