<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestController
{
    public function __construct(
        private \Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsHandler $contextOptions,
        private \Modules\Manifest\Application\UseCases\ListManifests\ListManifestsHandler $list,
        private \Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestHandler $create,
        private \Modules\Manifest\Application\UseCases\GetManifest\GetManifestHandler $get,
        private \Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestHandler $update,
        private \Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesHandler $eligible,
        private \Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsHandler $add,
        private \Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestHandler $validate,
        private \Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestHandler $confirm,
        private \Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionHandler $exception,
        private \Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionHandler $approveException,
        private \Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionHandler $rejectException,
        private \Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionHandler $resubmitException,
        private \Modules\Manifest\Application\Services\ManifestReader $reader,
    )
    {
    }

    public function index(\Modules\Manifest\Presentation\Http\Requests\ListManifestsRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::paginated($request, $this->list->handle(new \Modules\Manifest\Application\UseCases\ListManifests\ListManifestsCommand($this->actor($request), $this->node($request), $input))->data, fn($row): array => $this->reader->listItem($row));
    }

    public function store(\Modules\Manifest\Presentation\Http\Requests\CreateManifestRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->create->handle(new \Modules\Manifest\Application\UseCases\CreateManifest\CreateManifestCommand($this->actor($request), $this->node($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function contextOptions(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->contextOptions->handle(new \Modules\Manifest\Application\UseCases\GetManifestContextOptions\GetManifestContextOptionsCommand($this->actor($request), $this->node($request)))->data);
    }

    public function show(Request $request, string $manifestId): JsonResponse
    {
        return ApiResponder::success($request, $this->get->handle(new \Modules\Manifest\Application\UseCases\GetManifest\GetManifestCommand($this->actor($request), $this->node($request), $manifestId))->data);
    }

    public function update(\Modules\Manifest\Presentation\Http\Requests\UpdateManifestRequest $request, string $manifestId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->update->handle(new \Modules\Manifest\Application\UseCases\UpdateManifest\UpdateManifestCommand($this->actor($request), $this->node($request), $manifestId, $input, $this->correlation($request)))->data);
    }

    public function eligible(
        \Modules\Manifest\Presentation\Http\Requests\ListManifestCandidatesRequest $request,
        string $manifestId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::paginated($request, $this->eligible->handle(new \Modules\Manifest\Application\UseCases\ListManifestCandidates\ListManifestCandidatesCommand($this->actor($request), $this->node($request), $manifestId, $input))->data, static fn($row): array => (array) $row);
    }

    public function addParcels(\Modules\Manifest\Presentation\Http\Requests\AddManifestParcelsRequest $request, string $manifestId): JsonResponse
    {
        $input = $request->validated();
        $result = $this->add->handle(new \Modules\Manifest\Application\UseCases\AddManifestParcels\AddManifestParcelsCommand($this->actor($request), $this->node($request), $manifestId, $input, $this->correlation($request)))->data;
        return ApiResponder::success($request, $result['detail'], ['input_outcomes' => $result['outcomes']]);
    }

    public function validateManifest(\Modules\Manifest\Presentation\Http\Requests\ValidateManifestRequest $request, string $manifestId): JsonResponse
    {
        return ApiResponder::success($request, $this->validate->handle(new \Modules\Manifest\Application\UseCases\ValidateManifest\ValidateManifestCommand($this->actor($request), $this->node($request), $manifestId, (int) $request->validated('expected_version'), $this->correlation($request)))->data);
    }

    public function confirm(\Modules\Manifest\Presentation\Http\Requests\ConfirmManifestRequest $request, string $manifestId): JsonResponse
    {
        $input = $request->validated();
        try {
            $detail = $this->confirm->handle(new \Modules\Manifest\Application\UseCases\ConfirmManifest\ConfirmManifestCommand($this->actor($request), $this->node($request), $manifestId, (int) $input['expected_version'], $this->correlation($request), $input['exception_reason_code'] ?? null, $input['exception_description'] ?? null))->data;
            return ApiResponder::success($request, $detail, status: in_array($detail['manifest_status'], ['NPU', 'NOK'], true) ? 202 : 200);
        } catch (ApiException $exception) {
            if ($exception->errorCode !== ApiErrorCode::ManifestNoSuccessfulParcels) {
                throw $exception;
            }
            return ApiResponder::error($request, $exception->errorCode, $exception->getMessage(), $exception->httpStatus, $exception->fieldErrors, $exception->details);
        }
    }

    public function exception(Request $request, string $manifestId): JsonResponse
    {
        return ApiResponder::success($request, $this->exception->handle(new \Modules\Manifest\Application\UseCases\GetManifestException\GetManifestExceptionCommand($this->actor($request), $this->node($request), $manifestId))->data);
    }

    public function approveException(
        \Modules\Manifest\Presentation\Http\Requests\ApproveManifestExceptionRequest $request,
        string $manifestId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->approveException->handle(new \Modules\Manifest\Application\UseCases\ApproveManifestException\ApproveManifestExceptionCommand($this->actor($request), $this->node($request), $manifestId, (int) $input['expected_version'], (int) $input['expected_exception_version'], $input['decision_reason'] ?? null, $this->correlation($request)))->data);
    }

    public function rejectException(
        \Modules\Manifest\Presentation\Http\Requests\RejectManifestExceptionRequest $request,
        string $manifestId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->rejectException->handle(new \Modules\Manifest\Application\UseCases\RejectManifestException\RejectManifestExceptionCommand($this->actor($request), $this->node($request), $manifestId, (int) $input['expected_version'], (int) $input['expected_exception_version'], (string) $input['rejection_reason'], $this->correlation($request)))->data);
    }

    public function resubmitException(
        \Modules\Manifest\Presentation\Http\Requests\ResubmitManifestExceptionRequest $request,
        string $manifestId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->resubmitException->handle(new \Modules\Manifest\Application\UseCases\ResubmitManifestException\ResubmitManifestExceptionCommand($this->actor($request), $this->node($request), $manifestId, (int) $input['expected_version'], (int) $input['expected_exception_version'], (string) $input['reason_code'], (string) $input['description'], $this->correlation($request)))->data, status: 202);
    }

    private function actor(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }

    private function node(Request $request): string
    {
        $id = $request->attributes->get('node_id');
        if (!is_string($id) || $id === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational node is required.');
        }
        return $id;
    }
}
