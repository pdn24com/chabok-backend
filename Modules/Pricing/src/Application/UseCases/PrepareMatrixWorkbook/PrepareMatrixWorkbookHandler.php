<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook;

use Modules\Pricing\Application\Contracts\MatrixWorkbookPreviewServiceInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewResultDto;
use Modules\Pricing\Application\Dto\MatrixWorkbookSampleDto;
use Modules\Pricing\Application\Dto\WorkbookFileDto;

final readonly class PrepareMatrixWorkbookHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private MatrixWorkbookPreviewServiceInterface $matrixWorkbookPreviewService,
    ) {}

    public function handle(PrepareMatrixWorkbookCommand $command): MatrixWorkbookPreviewResultDto|WorkbookFileDto
    {
        $this->pricingAccessGuard->assertAccess($command->actor, 'pricing.tariff.manage_draft');
        if ($command->input instanceof MatrixWorkbookSampleDto) {
            return $this->matrixWorkbookPreviewService->sample($command->input->zoneTitles);
        }

        return $this->matrixWorkbookPreviewService->preview($command->input);
    }
}
