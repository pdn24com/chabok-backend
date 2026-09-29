<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Validators;

use Modules\Customer\Application\Contracts\CustomerContactPointValidatorInterface;
use Modules\Customer\Application\Dto\CustomerContactPointDraftDto;
use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Customer\Application\Repositories\RelationshipRepositoryInterface;
use Modules\Customer\Domain\Enums\ContactPointIdentifierKind;
use Modules\Customer\Domain\Enums\ContactPointStatus;
use Modules\Customer\Domain\Enums\ContactPointType;
use Modules\Customer\Domain\ValueObjects\MobileNumber;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CustomerContactPointValidator implements CustomerContactPointValidatorInterface
{
    public function __construct(
        private ContactPointRepositoryInterface $contactPointRepository,
        private CustomerAddressRepositoryInterface $customerAddressRepository,
        private RelationshipRepositoryInterface $relationshipRepository,
    ) {}

    public function validate(string $hqId, string $customerId, array $items, array $existingIds): array
    {
        $normalized = [];
        $seenIds = [];
        $seenChannels = [];
        $defaults = [];
        foreach ($items as $index => $item) {
            $path = 'items.'.$index;
            if ($item->id !== null) {
                // A row that names an ID must be one of this person's own; another person's row is not editable here.
                if (! in_array($item->id, $existingIds, true) || isset($seenIds[$item->id])) {
                    throw $this->invalid($path.'.id', 'customer.contact_point_is_not_of_this_customer');
                }
                $seenIds[$item->id] = true;
            }
            if ($item->identifierKind !== $item->type->identifierKind()) {
                throw $this->invalid($path.'.identifier_kind', 'customer.contact_point_identifier_kind_does_not_match_type');
            }

            $normalizedValue = $this->normalize($item, $path);
            $this->assertOwnReferences($hqId, $customerId, $item, $path);

            // One number is one channel of the person whatever its scope; every other channel may repeat across scopes.
            $channel = $item->type->value.'|'.($item->type === ContactPointType::MOBILE ? '' : $item->scope->value).'|'.$normalizedValue;
            if (isset($seenChannels[$channel])) {
                throw $this->invalid($path.'.value', 'customer.contact_point_is_duplicated');
            }
            $seenChannels[$channel] = true;

            if ($item->isDefault) {
                if ($item->status !== ContactPointStatus::ACTIVE) {
                    throw $this->invalid($path.'.is_default', 'customer.inactive_contact_point_cannot_be_default');
                }
                $slot = $item->type->value.'|'.$item->scope->value;
                if (isset($defaults[$slot])) {
                    throw $this->invalid($path.'.is_default', 'customer.only_one_default_contact_point_per_channel');
                }
                $defaults[$slot] = true;
            }
            $normalized[] = $normalizedValue;
        }

        $this->assertMobilesAreNotOwnedByOthers($hqId, $customerId, $items, $normalized);

        return $normalized;
    }

    private function normalize(CustomerContactPointDraftDto $item, string $path): string
    {
        $value = trim($item->value);

        return match ($item->identifierKind) {
            ContactPointIdentifierKind::PHONE => MobileNumber::tryFrom($value)?->normalized
                ?? throw $this->invalid($path.'.value', 'customer.mobile_number_is_invalid'),
            ContactPointIdentifierKind::EMAIL => $this->normalizeEmail($value, $path),
            ContactPointIdentifierKind::USERNAME => $this->normalizeUsername($value, $path),
            // The channel points at an address of the person; its stable key is that address's ID.
            ContactPointIdentifierKind::ADDRESS => $item->addressId
                ?? throw $this->invalid($path.'.address_id', 'customer.contact_point_address_is_required'),
        };
    }

    private function normalizeEmail(string $value, string $path): string
    {
        $email = mb_strtolower($value);
        if (mb_strlen($email) > 320 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw $this->invalid($path.'.value', 'customer.contact_point_value_is_invalid');
        }

        return $email;
    }

    /** A handle is stored without its leading "@" and in lower case, so "@Nima" and "nima" are one account. */
    private function normalizeUsername(string $value, string $path): string
    {
        $username = mb_strtolower(ltrim($value, '@'));
        if (preg_match('/^[a-z0-9._]{2,64}$/D', $username) !== 1) {
            throw $this->invalid($path.'.value', 'customer.contact_point_value_is_invalid');
        }

        return $username;
    }

    /** The relationship and the address of a channel must both belong to the person the set belongs to. */
    private function assertOwnReferences(string $hqId, string $customerId, CustomerContactPointDraftDto $item, string $path): void
    {
        if ($item->relationshipId !== null
            && ! $this->relationshipRepository->existsForPerson($hqId, $customerId, $item->relationshipId)) {
            throw $this->invalid($path.'.relationship_id', 'customer.contact_point_relationship_is_not_of_this_person');
        }
        if ($item->type === ContactPointType::ADDRESS_REFERENCE && $item->addressId === null) {
            throw $this->invalid($path.'.address_id', 'customer.contact_point_address_is_required');
        }
        if ($item->addressId !== null
            && $this->customerAddressRepository->findForCustomer($hqId, $customerId, $item->addressId) === null) {
            throw $this->invalid($path.'.address_id', 'customer.contact_point_address_is_not_of_this_person');
        }
    }

    /**
     * One mobile number, one person: an ACTIVE MOBILE channel of another person of the tenant refuses the
     * write. The candidate rows are read for update, so a concurrent writer waits for this transaction
     * instead of slipping the same number in beside it. Only MOBILE is guarded; a messenger or a landline
     * may be shared, exactly as the create-customer flow shares numbers.
     *
     * @param  list<CustomerContactPointDraftDto>  $items
     * @param  list<string>  $normalized
     */
    private function assertMobilesAreNotOwnedByOthers(string $hqId, string $customerId, array $items, array $normalized): void
    {
        foreach ($items as $index => $item) {
            if ($item->type !== ContactPointType::MOBILE || $item->status !== ContactPointStatus::ACTIVE) {
                continue;
            }
            $this->assertMobileIsFree($hqId, $normalized[$index], $customerId, 'items.'.$index.'.value');
        }
    }

    public function assertMobileIsFree(string $hqId, string $normalizedMobile, ?string $exceptCustomerId, string $field): void
    {
        $owners = $this->contactPointRepository->findActiveOwnersOfNormalizedValue(
            $hqId, ContactPointType::MOBILE->value, $normalizedMobile, exceptCustomerId: $exceptCustomerId, lock: true, limit: 1);
        if ($owners->isNotEmpty()) {
            throw new ApiException(ApiErrorCode::MobileOwnedByOtherPerson, 409, 'customer.mobile_is_owned_by_another_person',
                [$field => ['customer.mobile_is_owned_by_another_person']]);
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
