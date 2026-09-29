<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestContextInputDto;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;
use Modules\Manifest\Application\Dto\ManifestParcelInputDto;
use Modules\Manifest\Application\Serialization\ManifestParcelOutcomeDocument;
use Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsCommand;
use Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsHandler;
use Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestCommand;
use Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestHandler;
use Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestCommand;
use Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestHandler;
use Modules\Manifest\Application\UseCases\GetManifest\GetManifestCommand;
use Modules\Manifest\Application\UseCases\GetManifest\GetManifestHandler;
use Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsCommand;
use Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsHandler;
use Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesCommand;
use Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesHandler;
use Modules\Manifest\Application\UseCases\ListManifests\ListManifestsCommand;
use Modules\Manifest\Application\UseCases\ListManifests\ListManifestsHandler;
use Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionCommand;
use Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionHandler;
use Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestCommand;
use Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestHandler;
use Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestCommand;
use Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestHandler;
use Modules\Manifest\Presentation\Http\Requests\AddManifestParcelsRequest;
use Modules\Manifest\Presentation\Http\Requests\ApproveManifestExceptionRequest;
use Modules\Manifest\Presentation\Http\Requests\ConfirmManifestRequest;
use Modules\Manifest\Presentation\Http\Requests\CreateManifestRequest;
use Modules\Manifest\Presentation\Http\Requests\ListManifestCandidatesRequest;
use Modules\Manifest\Presentation\Http\Requests\ListManifestsRequest;
use Modules\Manifest\Presentation\Http\Requests\RejectManifestExceptionRequest;
use Modules\Manifest\Presentation\Http\Requests\ResubmitManifestExceptionRequest;
use Modules\Manifest\Presentation\Http\Requests\UpdateManifestRequest;
use Modules\Manifest\Presentation\Http\Requests\ValidateManifestRequest;
use Modules\Manifest\Presentation\Http\Resources\ManifestDetailResource;
use Modules\Manifest\Presentation\Http\Resources\ManifestExceptionResource;
use Modules\Manifest\Presentation\Http\Resources\ManifestListResource;

final class ManifestController
{
    public function index(ListManifestsRequest $request, ListManifestsHandler $listManifestsHandler, ManifestReaderInterface $manifestReader): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::paginated($request, $listManifestsHandler->handle(new ListManifestsCommand($request->attributes->get('principal'), $this->node($request), ManifestFiltersDto::fromArray($input))), fn ($row): array => (new ManifestListResource($manifestReader->listItem($row)))->resolve($request));
    }

    public function store(CreateManifestRequest $request, CreateManifestHandler $createManifestHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new ManifestDetailResource($createManifestHandler->handle(new CreateManifestCommand($request->attributes->get('principal'), $this->node($request), ManifestContextInputDto::fromArray($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function contextOptions(Request $request, GetManifestContextOptionsHandler $getManifestContextOptionsHandler): JsonResponse
    {
        return ApiResponder::success($request, $getManifestContextOptionsHandler->handle(new GetManifestContextOptionsCommand($request->attributes->get('principal'), $this->node($request))));
    }

    public function show(Request $request, GetManifestHandler $getManifestHandler, string $manifestId): JsonResponse
    {
        return ApiResponder::success($request, (new ManifestDetailResource($getManifestHandler->handle(new GetManifestCommand($request->attributes->get('principal'), $this->node($request), $manifestId))))->resolve($request));
    }

    public function update(UpdateManifestRequest $request, UpdateManifestHandler $updateManifestHandler, string $manifestId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new ManifestDetailResource($updateManifestHandler->handle(new UpdateManifestCommand($request->attributes->get('principal'), $this->node($request), $manifestId, ManifestContextInputDto::fromArray($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function eligible(ListManifestCandidatesRequest $request, ListManifestCandidatesHandler $listManifestCandidatesHandler, string $manifestId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::paginated($request, $listManifestCandidatesHandler->handle(new ListManifestCandidatesCommand($request->attributes->get('principal'), $this->node($request), $manifestId, ManifestFiltersDto::fromArray($input))), static fn ($row): array => Arr::except((array) $row, ['id']));
    }

    public function addParcels(AddManifestParcelsRequest $request, AddManifestParcelsHandler $addManifestParcelsHandler, string $manifestId): JsonResponse
    {
        $input = $request->validated();
        $result = $addManifestParcelsHandler->handle(new AddManifestParcelsCommand($request->attributes->get('principal'), $this->node($request), $manifestId, ManifestParcelInputDto::fromArray($input), (string) $request->attributes->get('correlation_id')));

        return ApiResponder::success($request, (new ManifestDetailResource($result->detail))->resolve($request), ['input_outcomes' => array_map(ManifestParcelOutcomeDocument::serialize(...), $result->outcomes)]);
    }

    public function validateManifest(ValidateManifestRequest $request, ValidateManifestHandler $validateManifestHandler, string $manifestId): JsonResponse
    {
        return ApiResponder::success($request, (new ManifestDetailResource($validateManifestHandler->handle(new ValidateManifestCommand($request->attributes->get('principal'), $this->node($request), $manifestId, (int) $request->validated('expected_version'), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function confirm(ConfirmManifestRequest $request, ConfirmManifestHandler $confirmManifestHandler, string $manifestId): JsonResponse
    {
        $input = $request->validated();
        try {
            $detail = $confirmManifestHandler->handle(new ConfirmManifestCommand($request->attributes->get('principal'), $this->node($request), $manifestId, (int) $input['expected_version'], (string) $request->attributes->get('correlation_id'), $input['exception_reason_code'] ?? null, $input['exception_description'] ?? null));

            return ApiResponder::success($request, (new ManifestDetailResource($detail))->resolve($request), status: in_array($detail->list->manifest->manifest_status, ['NPU', 'NOK'], true) ? 202 : 200);
        } catch (ApiException $exception) {
            if ($exception->errorCode !== ApiErrorCode::ManifestNoSuccessfulParcels) {
                throw $exception;
            }

            return ApiResponder::error($request, $exception->errorCode, $exception->messageKey, $exception->httpStatus, $exception->fieldErrors, $exception->details, $exception->messageParams);
        }
    }

    public function exception(Request $request, GetManifestExceptionHandler $getManifestExceptionHandler, string $manifestId): JsonResponse
    {
        return ApiResponder::success($request, (new ManifestExceptionResource($getManifestExceptionHandler->handle(new GetManifestExceptionCommand($request->attributes->get('principal'), $this->node($request), $manifestId))))->resolve($request));
    }

    public function approveException(ApproveManifestExceptionRequest $request, ApproveManifestExceptionHandler $approveManifestExceptionHandler, string $manifestId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new ManifestDetailResource($approveManifestExceptionHandler->handle(new ApproveManifestExceptionCommand($request->attributes->get('principal'), $this->node($request), $manifestId, (int) $input['expected_version'], (int) $input['expected_exception_version'], $input['decision_reason'] ?? null, (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function rejectException(RejectManifestExceptionRequest $request, RejectManifestExceptionHandler $rejectManifestExceptionHandler, string $manifestId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new ManifestDetailResource($rejectManifestExceptionHandler->handle(new RejectManifestExceptionCommand($request->attributes->get('principal'), $this->node($request), $manifestId, (int) $input['expected_version'], (int) $input['expected_exception_version'], (string) $input['rejection_reason'], (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function resubmitException(ResubmitManifestExceptionRequest $request, ResubmitManifestExceptionHandler $resubmitManifestExceptionHandler, string $manifestId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new ManifestDetailResource($resubmitManifestExceptionHandler->handle(new ResubmitManifestExceptionCommand($request->attributes->get('principal'), $this->node($request), $manifestId, (int) $input['expected_version'], (int) $input['expected_exception_version'], (string) $input['reason_code'], (string) $input['description'], (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 202);
    }

    private function node(Request $request): string
    {
        $id = $request->attributes->get('node_id');
        if (! is_string($id) || $id === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'common.active_operational_node_is_required');
        }

        return $id;
    }
}
