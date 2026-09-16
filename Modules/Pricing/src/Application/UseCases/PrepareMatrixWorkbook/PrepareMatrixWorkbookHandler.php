<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PrepareMatrixWorkbookHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\MatrixWorkbook $workbooks,
    ) {
    }

    public function handle(PrepareMatrixWorkbookCommand $command): PrepareMatrixWorkbookResult
    {
        return new PrepareMatrixWorkbookResult($this->execute($command->actor, $command->input, $command->sample));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, bool $sample): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $workbooks = $this->workbooks;
        return $sample ? $workbooks->sample($input['zone_titles']) : $workbooks->preview($input['content_base64'], $input['matrix']);
    }
}
