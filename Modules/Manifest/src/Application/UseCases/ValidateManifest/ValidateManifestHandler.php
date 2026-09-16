<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ValidateManifest;

use Modules\Manifest\Domain\ManifestEligibilityReason;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateManifestHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
        private \Modules\Manifest\Domain\ManifestVersionGuard $manifestVersionGuard,
        private \Modules\Manifest\Domain\ManifestPolicy $policy,
        private \Modules\Manifest\Application\Repositories\ManifestRepository $manifests,
        private \Modules\Consignment\Application\Contracts\ManifestConsignmentAccess $consignmentState,
        private \Modules\Manifest\Application\ManifestEligibilityEvaluator $eligibility,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
    )
    {
    }

    public function handle(ValidateManifestCommand $command): ValidateManifestResult
    {
        return new ValidateManifestResult($this->execute($command->actor, $command->nodeId, $command->id, $command->expected, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id, int $expected, string $correlationId): array
    {
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.edit');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $expected, $correlationId): void {
            $m = $this->manifestReader->locked($actor, $nodeId, $id);
            $this->manifestVersionGuard->version($m, $expected);
            $this->policy->assertEditable((string) $m->state);
            foreach ($this->manifests->lockValidationRows($id) as $row) {
                $parcel = $this->consignmentState->parcel((string) $actor->hqId, (string) $row->parcel_id);
                $eligibility = $parcel === null ? ManifestEligibilityReason::metadata(ManifestEligibilityReason::ParcelNotFound) : $this->eligibility->evaluate($parcel, $m, $nodeId);
                $this->manifests->updateParcel($row->manifest_parcel_id, $eligibility['eligible'] ? ['manifest_parcel_status' => 'VALIDATED', 'updated_at' => $this->clock->now()] : [
                    'manifest_parcel_status' => 'FAILED',
                    'failure_code' => $eligibility['reason_code'],
                    'failure_reason' => $eligibility['presentation']['detail']['en'],
                    'active_slot' => null,
                    'processed_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            }
            $this->manifests->update($id, ['state' => 'OPEN', 'version' => $m->version + 1, 'updated_at' => $this->clock->now()]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_VALIDATED', 'MANIFEST', $id, $correlationId);
        });
        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
