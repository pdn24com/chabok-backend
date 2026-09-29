<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Services;

use Modules\Customer\Application\Contracts\CustomerRelationshipAssemblerInterface;
use Modules\Customer\Application\Dto\CustomerRelationshipDto;
use Modules\Customer\Application\Repositories\CustomerPositionRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\RelationshipRecord;

final readonly class CustomerRelationshipAssembler implements CustomerRelationshipAssemblerInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private CustomerPositionRepositoryInterface $customerPositionRepository,
    ) {}

    public function assemble(string $hqId, iterable $relationships): array
    {
        $rows = [...$relationships];
        $customerIds = [];
        $positionIds = [];
        foreach ($rows as $row) {
            $customerIds[$row->person_customer_id] = true;
            $customerIds[$row->company_customer_id] = true;
            if ($row->position_id !== null) {
                $positionIds[$row->position_id] = true;
            }
        }
        $names = $this->customerRepository->displayNamesFor($hqId, array_map('strval', array_keys($customerIds)));
        $titles = $this->customerPositionRepository->titlesFor($hqId, array_map('strval', array_keys($positionIds)));

        return array_map(static fn (RelationshipRecord $row): CustomerRelationshipDto => new CustomerRelationshipDto(
            relationshipId: $row->relationship_id,
            personCustomerId: $row->person_customer_id,
            personDisplayName: $names[$row->person_customer_id] ?? null,
            companyCustomerId: $row->company_customer_id,
            companyDisplayName: $names[$row->company_customer_id] ?? null,
            positionId: $row->position_id,
            positionTitle: $row->position_id === null ? null : ($titles[$row->position_id] ?? null),
            roleTitle: $row->role_title,
            decisionLevel: $row->decision_level,
            signingAuthority: $row->signing_authority,
            validFrom: $row->valid_from,
            validTo: $row->valid_to,
            isPrimary: (bool) $row->is_primary,
        ), $rows);
    }
}
