<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Consignment\Application\Contracts\ConsignmentQuoteInputInterface;
use Modules\Consignment\Application\Dto\ConsignmentContactDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Geography\Application\Contracts\GeographyResolverInterface;

final readonly class ConsignmentQuoteInput implements ConsignmentQuoteInputInterface
{
    public function __construct(private GeographyResolverInterface $geographyResolver) {}

    public function normalized(ConsignmentDraftDto $source): ConsignmentDraftDto
    {
        $input = clone $source;
        // Server-resolved evidence is fingerprinted separately, never accepted as editable input.
        $input->commitmentScheduleVersionId = null;
        $input->pickupCommitmentStartAt = null;
        $input->pickupCommitmentEndAt = null;
        $input->deliveryCommitmentStartAt = null;
        $input->deliveryCommitmentEndAt = null;
        $input->commitmentSnapshot = null;
        $input->deliveryCommitmentResolution = null;
        $input->sender = $this->contact($source->sender);
        $input->receiver = $this->contact($source->receiver);
        $input->selectedOptionVersionIds = array_values($source->selectedOptionVersionIds);
        $input->pickupCommitmentAt = $source->pickupCommitmentAt === null ? null : CarbonImmutable::parse($source->pickupCommitmentAt)->utc()->toISOString();
        $input->deliveryCommitmentAt = $source->deliveryCommitmentAt === null ? null : CarbonImmutable::parse($source->deliveryCommitmentAt)->utc()->toISOString();
        $input->parcels = [];
        foreach ($source->parcels as $sourceParcel) {
            $parcel = clone $sourceParcel;
            $parcel->contentDescription = trim($sourceParcel->contentDescription ?? '');
            $parcel->presentFields = array_values(array_unique([...$sourceParcel->presentFields, 'weight_kg', 'width_cm', 'length_cm', 'height_cm', 'content_description']));
            $input->parcels[] = $parcel;
        }
        $input->presentFields = array_values(array_unique([...$source->presentFields,
            'pickup_commitment_at',
            'delivery_commitment_at',
            'width_cm',
            'length_cm',
            'height_cm',
            'insurance_value_amount',
            'cod_amount',
            'service_offering_id',
            'service_offering_version_id',
            'pickup_service_date',
            'pickup_window_code',
            'delivery_window_code',
            'commitment_schedule_version_id',
            'pickup_commitment_start_at',
            'pickup_commitment_end_at',
            'delivery_commitment_start_at',
            'delivery_commitment_end_at',
            'commitment_snapshot',
            'delivery_commitment_resolution',
            'selected_option_version_ids',
            'sender',
            'receiver',
            'parcels',
        ]));

        return $input;
    }

    private function contact(ConsignmentContactDto $input): ConsignmentContactDto
    {
        $contact = ConsignmentInputMapper::contact($this->geographyResolver->canonicalizeContact(ConsignmentDraftDocument::contact($input), true));
        $contact->cityReference = null;
        $contact->presentFields = array_values(array_diff(array_unique([...$contact->presentFields, 'address_book_entry_id', 'phone', 'country', 'postal_code', 'latitude', 'longitude']), ['city_reference']));

        return $contact;
    }
}
