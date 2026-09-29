<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\CrmTeam\Application\UseCases\CreateTeam\CreateTeamHandler;
use Modules\CrmTeam\Application\UseCases\ListTeams\ListTeamsHandler;
use Modules\CrmTeam\Application\UseCases\UpdateTeam\UpdateTeamHandler;
use Modules\CrmTeam\Presentation\Http\Requests\CreateTeamRequest;
use Modules\CrmTeam\Presentation\Http\Requests\ListTeamsRequest;
use Modules\CrmTeam\Presentation\Http\Requests\UpdateTeamRequest;
use Modules\CrmTeam\Presentation\Http\Resources\TeamResource;
use Modules\CrmTeam\Presentation\Mappers\TeamCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** CRM work teams: the whole set, a new one, and a change to one. */
final class TeamController
{
    public function index(ListTeamsRequest $request, ListTeamsHandler $handler): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::listing($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, TeamResource::collection($result->teams)->resolve($request));
    }

    public function store(CreateTeamRequest $request, CreateTeamHandler $handler): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::draft($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, [
            ...(new TeamResource($result->team))->resolve($request),
            // The supervisor's own membership is written by the same call, so its ID is answered with it.
            'membership_id' => $result->supervisorMembership->team_member_id,
        ], status: 201);
    }

    public function update(UpdateTeamRequest $request, UpdateTeamHandler $handler, string $teamId): JsonResponse
    {
        $result = $handler->handle(TeamCommandMapper::changes($request->attributes->get('principal'), $teamId, $request->validated()));

        return ApiResponder::success($request, new TeamResource($result->team));
    }
}
