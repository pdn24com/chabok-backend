<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerContactPoints;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerContactPointValidatorInterface;
use Modules\Customer\Application\Dto\CustomerContactPointDraftDto;
use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class SaveCustomerContactPointsHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ContactPointRepositoryInterface $contactPointRepository,
        private CustomerContactPointValidatorInterface $customerContactPointValidator,
    ) {}

    public function handle(SaveCustomerContactPointsCommand $command): SaveCustomerContactPointsResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);

        $contactPoints = $this->connection->transaction(function () use ($hqId, $command): Collection {
            // The person's row is locked first, so two replacements of the same set run one after the other.
            $customer = $this->customerRepository->lockForTenant($hqId, $command->customerId);
            if ($customer === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($customer->kind !== CustomerKind::PERSON) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['customer_id' => ['customer.contact_points_belong_to_persons_only']]);
            }

            $existing = $this->contactPointRepository->lockForCustomer($hqId, $command->customerId)
                ->keyBy(static fn (ContactPointRecord $row): string => $row->contact_point_id);
            $existingIds = $existing->map(static fn (ContactPointRecord $row): string => $row->contact_point_id)->values()->all();
            $normalized = $this->customerContactPointValidator->validate($hqId, $command->customerId, $command->items, $existingIds);

            $keptIds = array_values(array_filter(array_map(static fn (CustomerContactPointDraftDto $item): ?string => $item->id, $command->items)));
            // Nothing references a contact point, so a channel left out of the set is simply removed.
            // Deleting first frees a default flag before another row claims it.
            $this->contactPointRepository->deleteForCustomer($hqId, $command->customerId,
                array_values(array_filter($existingIds, static fn (string $id): bool => ! in_array($id, $keptIds, true))));

            // Updates that give up their default flag run before the rest, and creations run last, so at no
            // moment do two active defaults of one channel and scope stand side by side.
            $updates = [];
            $creations = [];
            foreach ($command->items as $index => $item) {
                $item->id === null ? $creations[$index] = $item : $updates[$index] = $item;
            }
            uasort($updates, static fn (CustomerContactPointDraftDto $a, CustomerContactPointDraftDto $b): int => (int) $a->isDefault <=> (int) $b->isDefault);

            $now = $this->clock->now();
            foreach ($updates as $index => $item) {
                $current = $existing->get($item->id);
                $this->contactPointRepository->update($hqId, $item->id, $this->attributes($item, $normalized[$index], $current->verified_manually_at, $now));
            }
            foreach ($creations as $index => $item) {
                $this->contactPointRepository->create([
                    'hq_id' => $hqId,
                    'customer_id' => $command->customerId,
                    ...$this->attributes($item, $normalized[$index], null, $now),
                    'created_by' => $command->actor->userId,
                    'created_at' => $now,
                ]);
            }

            return $this->contactPointRepository->listForCustomer($hqId, $command->customerId);
        }, attempts: 3);

        return new SaveCustomerContactPointsResult($contactPoints);
    }

    /**
     * The writable columns of one item. A manual review keeps the time of the first review when the flag
     * stays on and is cleared when it goes off.
     *
     * @return array<string, mixed>
     */
    private function attributes(CustomerContactPointDraftDto $item, string $normalizedValue, mixed $verifiedAt, DateTimeImmutable $now): array
    {
        return [
            'type' => $item->type->value,
            'identifier_kind' => $item->identifierKind->value,
            'value' => trim($item->value),
            'normalized_value' => $normalizedValue,
            'scope' => $item->scope->value,
            'is_default' => $item->isDefault,
            'status' => $item->status->value,
            'priority' => $item->priority,
            'subtype' => $item->subtype,
            'work_context' => $item->workContext,
            'relationship_id' => $item->relationshipId,
            'address_id' => $item->addressId,
            'verified_manually_at' => $item->verifiedManually ? ($verifiedAt ?? $now) : null,
        ];
    }
}
