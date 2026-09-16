<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\CreatePricingChargeType;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreatePricingChargeTypeHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function handle(CreatePricingChargeTypeCommand $command): CreatePricingChargeTypeResult
    {
        return new CreatePricingChargeTypeResult($this->execute($command->actor, $command->input));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $id = $this->identifiers->uuid();
        $this->pricing->insertChargeType(['charge_type_id' => $id, ...$input, 'created_at' => $this->clock->now(), 'updated_at' => $this->clock->now()]);
        return (array) $this->pricing->chargeType($id);
    }
}
