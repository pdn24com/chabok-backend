<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

/** The child collections an Offering version owns; each is replaced wholesale when the draft is saved. */
enum OfferingChild: string
{
    public function primaryKey(): string
    {
        return $this->value;
    }
    case OptionRule = 'offering_option_rule_id';
    case EligibilityRule = 'eligibility_rule_id';
    case CoverageReference = 'coverage_reference_id';
    case AvailabilityBinding = 'availability_binding_id';
    case CommitmentBinding = 'offering_commitment_binding_id';
}
