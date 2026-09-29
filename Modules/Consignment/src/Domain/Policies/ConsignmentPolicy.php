<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Policies;

use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Domain\Exceptions\ConsignmentRuleViolation;
use Modules\Foundation\Domain\Enums\ApiErrorCode;

final class ConsignmentPolicy
{
    /** @param list<string> $editableStatuses */
    public function assertEditable(string $status, array $editableStatuses): void
    {
        if (! in_array($status, $editableStatuses, true)) {
            throw new ConsignmentRuleViolation(ApiErrorCode::ConsignmentNotEditable, 'consignment.consignment_can_no_longer_be_edited');
        }
    }

    public function assertCommercialConsistency(ConsignmentDraftDto $input): void
    {
        $this->assertOptionalAmount((bool) $input->insuranceEnabled, $input->insuranceValueAmount, 'insurance_value_amount');
        $this->assertOptionalAmount((bool) $input->codEnabled, $input->codAmount, 'cod_amount');
        $dimensions = [$input->widthCm ?? null, $input->lengthCm ?? null, $input->heightCm ?? null];
        $present = array_filter($dimensions, static fn ($value): bool => $value !== null);
        if ($present !== [] && count($present) !== 3) {
            throw new ConsignmentRuleViolation(ApiErrorCode::ValidationError, 'consignment.all_dimensions_must_be_provided_together', ['dimensions' => ['consignment.dimensions_are_required_together']]);
        }
    }

    public function assertPilotCreate(ConsignmentDraftDto $input): void
    {
        if (($input->insuranceEnabled ?? false) !== true || (int) ($input->insuranceValueAmount ?? -1) !== (int) ($input->declaredValueAmount ?? 0)) {
            throw new ConsignmentRuleViolation(ApiErrorCode::ValidationError, 'consignment.declared_value_insurance_is_mandatory_for_pilot', reasonCode: 'MANDATORY_INSURANCE_REQUIRED');
        }
        if (! in_array($input->payer ?? null, ['SENDER', 'RECEIVER'], true)) {
            throw new ConsignmentRuleViolation(ApiErrorCode::ValidationError, 'consignment.payer_is_not_available_for_pilot', reasonCode: 'PILOT_PAYER_INVALID');
        }
        if (! in_array($input->paymentMethod ?? null, ['CASH', 'CREDIT'], true)) {
            throw new ConsignmentRuleViolation(ApiErrorCode::ValidationError, 'consignment.payment_method_is_not_available_for_pilot', reasonCode: 'PILOT_PAYMENT_METHOD_INVALID');
        }
    }

    private function assertOptionalAmount(
        bool $enabled,
        int|string|null $amount,
        string $amountKey,
    ): void {
        if ($enabled && $amount === null || ! $enabled && $amount !== null) {
            throw new ConsignmentRuleViolation(ApiErrorCode::ValidationError, 'consignment.commercial_amount_and_selection_do_not_agree', [$amountKey => ['consignment.amount_must_be_present_only_when_enabled']]);
        }
    }
}
