<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingChargeType;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Repositories\PricingChargeTypeRepositoryInterface;

final readonly class CreatePricingChargeTypeHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ClockInterface $clock,
        private PricingChargeTypeRepositoryInterface $pricingChargeTypeRepository,
    ) {}

    public function handle(CreatePricingChargeTypeCommand $command): array
    {
        $actor = $command->actor;
        $input = $command->input;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $id = $this->pricingChargeTypeRepository->create([

            'code' => $input->code, 'category' => $input->category->value, 'accounting_mapping_key' => $input->accountingMappingKey,
            'taxable' => $input->taxable, 'active' => $input->active,
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);

        return $this->pricingChargeTypeRepository->sole($id)->attributesToArray();
    }
}
