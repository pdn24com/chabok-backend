<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmTeam\Application\UseCases\AddTeamMember\AddTeamMemberHandler;
use Modules\CrmTeam\Application\UseCases\EndTeamMembership\EndTeamMembershipHandler;
use Modules\CrmTeam\Application\UseCases\ListTeamMembers\ListTeamMembersHandler;
use Modules\CrmTeam\Application\UseCases\RunBulkMembershipChange\RunBulkMembershipChangeHandler;
use Modules\CrmTeam\Presentation\Http\Requests\AddTeamMemberRequest;
use Modules\CrmTeam\Presentation\Http\Requests\EndTeamMembershipRequest;
use Modules\CrmTeam\Presentation\Http\Requests\ListTeamMembersRequest;
use Modules\CrmTeam\Presentation\Http\Requests\RunBulkMembershipChangeRequest;
use Modules\CrmTeam\Presentation\Http\Resources\BulkMembershipResultResource;
use Modules\CrmTeam\Presentation\Http\Resources\TeamMemberResource;
use Modules\CrmTeam\Presentation\Mappers\TeamCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** Who is in which CRM team: the member list, one enrolment, one ending, and the bulk run over many. */
final class TeamMemberController
{
    public function index(ListTeamMembersRequest $request, ListTeamMembersHandler $handler): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::memberListing($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::paginated($request, $result->members, fn ($member): array => (new TeamMemberResource($member))->resolve($request));
    }

    public function store(AddTeamMemberRequest $request, AddTeamMemberHandler $handler, string $teamId): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::member($request->attributes->get('principal'), $teamId, $request->validated()));

        return ApiResponder::success($request, new TeamMemberResource($result->membership), status: 201);
    }

    public function end(EndTeamMembershipRequest $request, EndTeamMembershipHandler $handler, string $membershipId): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::membershipEnd($request->attributes->get('principal'), $membershipId, $request->validated()));

        return ApiResponder::success($request, new TeamMemberResource($result->membership));
    }

    public function bulk(RunBulkMembershipChangeRequest $request, RunBulkMembershipChangeHandler $handler): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::bulk($request->attributes->get('principal'), $request->validated(), false));

        return ApiResponder::success($request, new BulkMembershipResultResource($result));
    }

    public function bulkPreview(RunBulkMembershipChangeRequest $request, RunBulkMembershipChangeHandler $handler): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::bulk($request->attributes->get('principal'), $request->validated(), true));

        return ApiResponder::success($request, new BulkMembershipResultResource($result));
    }
}
