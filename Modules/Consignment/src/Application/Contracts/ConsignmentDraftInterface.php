<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\ConsignmentContactDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentParcelDto;
use Modules\Consignment\Application\Dto\ConsignmentPricingOptionDto;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;

interface ConsignmentDraftInterface
{
    public function draftFromRow(ConsignmentRecord $row): ConsignmentDraftDto;

    public function commercialColumns(ConsignmentDraftDto $input): array;

    public function contactColumns(string $prefix, ConsignmentContactDto $contact): array;

    public function contactFromRow(string $prefix, ConsignmentRecord $row): ConsignmentContactDto;

    public function parcelPhysical(ConsignmentParcelDto $parcel, ConsignmentDraftDto $aggregate): array;

    public function withAcceptedCommitment(ConsignmentDraftDto $input, ?array $commitment): ConsignmentDraftDto;

    public function withAcceptedOffering(ConsignmentDraftDto $draft, ConsignmentPricingOptionDto $option): ConsignmentDraftDto;
}
