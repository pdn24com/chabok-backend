<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\AddManifestParcels;

use Modules\Manifest\Domain\ManifestEligibilityReason;
use Modules\Manifest\Domain\ManifestWriteConflict;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AddManifestParcelsHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
        private \Modules\Manifest\Domain\ManifestVersionGuard $manifestVersionGuard,
        private \Modules\Manifest\Domain\ManifestPolicy $policy,
        private \Modules\Manifest\Application\Repositories\ManifestRepository $manifests,
        private \Modules\Manifest\Application\ManifestEligibilityEvaluator $eligibility,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
    )
    {
    }

    public function handle(AddManifestParcelsCommand $command): AddManifestParcelsResult
    {
        return new AddManifestParcelsResult($this->execute($command->actor, $command->nodeId, $command->id, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $input, string $correlationId): array
    {
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.edit');
        $outcomes = $this->transactions->run(function () use ($actor, $nodeId, $id, $input, $correlationId): array {
            $manifest = $this->manifestReader->locked($actor, $nodeId, $id);
            $this->manifestVersionGuard->version($manifest, (int) $input['expected_version']);
            $this->policy->assertEditable((string) $manifest->state);
            $outcomes = [];
            $inserted = 0;
            foreach ($input['identifiers'] as $identifier) {
                $parcels = $this->manifests->resolveInput((string) $actor->hqId, $nodeId, (string) $identifier);
                if ($parcels === []) {
                    $reason = ManifestEligibilityReason::metadata(ManifestEligibilityReason::ParcelNotFound);
                    $outcomes[] = ['input' => $identifier, 'result' => 'NOT_FOUND', ...$reason];
                    continue;
                }
                foreach ($parcels as $parcel) {
                    if ($this->manifests->containsParcel($parcel->parcel_id, $id)) {
                        $outcomes[] = [
                            'input' => $identifier,
                            'parcel_number' => $parcel->parcel_number,
                            'result' => 'DUPLICATE',
                            ...$this->eligibility->evaluate($parcel, $manifest, $nodeId),
                        ];
                        continue;
                    }
                    $eligibility = $this->eligibility->evaluate($parcel, $manifest, $nodeId);
                    $slot = hash('sha256', "{$actor->hqId}|{$parcel->parcel_id}|{$manifest->manifest_status}");
                    $row = [
                        'manifest_parcel_id' => $this->identifiers->uuid(),
                        'hq_id' => $actor->hqId,
                        'manifest_id' => $id,
                        'parcel_id' => $parcel->parcel_id,
                        'manifest_parcel_status' => $eligibility['eligible'] ? 'PENDING' : 'FAILED',
                        'failure_code' => $eligibility['eligible'] ? null : $eligibility['reason_code'],
                        'failure_reason' => $eligibility['eligible'] ? null : $eligibility['presentation']['detail']['en'],
                        'input_source' => $input['input_source'],
                        'input_value' => $identifier,
                        'active_slot' => $eligibility['eligible'] ? $slot : null,
                        'created_by' => $actor->userId,
                        'processed_at' => $eligibility['eligible'] ? null : $this->clock->now(),
                        'created_at' => $this->clock->now(),
                        'updated_at' => $this->clock->now(),
                    ];
                    try {
                        $this->manifests->insertParcel($row);
                        $result = $eligibility['eligible'] ? 'ADDED' : 'FAILED';
                    } catch (ManifestWriteConflict $e) {
                        $eligibility = ManifestEligibilityReason::metadata(ManifestEligibilityReason::ParcelAlreadyAssigned);
                        $row['manifest_parcel_id'] = $this->identifiers->uuid();
                        $row['manifest_parcel_status'] = 'FAILED';
                        $row['failure_code'] = $eligibility['reason_code'];
                        $row['failure_reason'] = $eligibility['presentation']['detail']['en'];
                        $row['active_slot'] = null;
                        $row['processed_at'] = $this->clock->now();
                        $this->manifests->insertParcel($row);
                        $result = 'FAILED';
                    }
                    $inserted++;
                    $outcomes[] = ['input' => $identifier, 'parcel_number' => $parcel->parcel_number, 'result' => $result, ...$eligibility];
                }
            }
            if ($inserted > 0) {
                $this->manifests->update($id, ['version' => $manifest->version + 1, 'updated_at' => $this->clock->now()]);
                $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_PARCELS_ADDED', 'MANIFEST', $id, $correlationId);
            }
            return $outcomes;
        });
        return ['detail' => $this->manifestReader->detail($actor, $nodeId, $id, $context), 'outcomes' => $outcomes];
    }
}
