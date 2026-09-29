<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Mappers;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Customer\Application\Dto\CompanyRelationshipDraftDto;
use Modules\Customer\Application\Dto\LeadConversionDto;
use Modules\Customer\Application\Dto\NewPersonDto;
use Modules\Customer\Application\UseCases\ChangeCustomerLifecycle\ChangeCustomerLifecycleCommand;
use Modules\Customer\Application\UseCases\ConvertLead\ConvertLeadCommand;
use Modules\Customer\Application\UseCases\CreateCompanyRelationship\CreateCompanyRelationshipCommand;
use Modules\Customer\Application\UseCases\EndCustomerRelationship\EndCustomerRelationshipCommand;
use Modules\Customer\Application\UseCases\ListCustomerRelationships\ListCustomerRelationshipsCommand;
use Modules\Customer\Application\Dto\CustomerContactPointDraftDto;
use Modules\Customer\Application\Dto\CustomerIndustryDraftDto;
use Modules\Customer\Application\Dto\CustomerAddressChangesDto;
use Modules\Customer\Application\Dto\CustomerAddressDraftDto;
use Modules\Customer\Application\Dto\CustomerAddressDto;
use Modules\Customer\Application\Dto\CustomerDepartmentChangesDto;
use Modules\Customer\Application\Dto\CustomerDepartmentDraftDto;
use Modules\Customer\Application\Dto\CustomerDraftDto;
use Modules\Customer\Application\Dto\CustomerExtendedDetailsDto;
use Modules\Customer\Application\Dto\CustomerFinancialDetailsDto;
use Modules\Customer\Application\Dto\CustomerListFiltersDto;
use Modules\Customer\Application\Dto\CustomerPositionChangesDto;
use Modules\Customer\Application\Dto\CustomerPositionDraftDto;
use Modules\Customer\Application\Dto\CustomerProfileChangesDto;
use Modules\Customer\Application\UseCases\CreateCustomer\CreateCustomerCommand;
use Modules\Customer\Application\UseCases\CreateCustomerAddress\CreateCustomerAddressCommand;
use Modules\Customer\Application\UseCases\CreateCustomerDepartment\CreateCustomerDepartmentCommand;
use Modules\Customer\Application\UseCases\CreateCustomerPosition\CreateCustomerPositionCommand;
use Modules\Customer\Application\UseCases\FindCustomerDuplicates\FindCustomerDuplicatesCommand;
use Modules\Customer\Application\UseCases\ListCustomerContactPoints\ListCustomerContactPointsCommand;
use Modules\Customer\Application\UseCases\ListCustomerIndustries\ListCustomerIndustriesCommand;
use Modules\Customer\Application\UseCases\SaveCustomerContactPoints\SaveCustomerContactPointsCommand;
use Modules\Customer\Application\UseCases\SaveCustomerIndustries\SaveCustomerIndustriesCommand;
use Modules\Customer\Application\UseCases\GetCustomerAddress\GetCustomerAddressCommand;
use Modules\Customer\Application\UseCases\GetCustomerDetail\GetCustomerDetailCommand;
use Modules\Customer\Application\UseCases\GetCustomerExtendedDetails\GetCustomerExtendedDetailsCommand;
use Modules\Customer\Application\UseCases\GetCustomerFinancialDetails\GetCustomerFinancialDetailsCommand;
use Modules\Customer\Application\UseCases\GetCustomerHistory\GetCustomerHistoryCommand;
use Modules\Customer\Application\UseCases\GetCustomerOrgStructure\GetCustomerOrgStructureCommand;
use Modules\Customer\Application\UseCases\GetCustomerProfile\GetCustomerProfileCommand;
use Modules\Customer\Application\UseCases\ListCustomerAddresses\ListCustomerAddressesCommand;
use Modules\Customer\Application\UseCases\ListCustomerHistoryEntries\ListCustomerHistoryEntriesCommand;
use Modules\Customer\Application\UseCases\ListCustomers\ListCustomersCommand;
use Modules\Customer\Application\UseCases\SaveCustomerExtendedDetails\SaveCustomerExtendedDetailsCommand;
use Modules\Customer\Application\UseCases\SaveCustomerFinancialDetails\SaveCustomerFinancialDetailsCommand;
use Modules\Customer\Application\UseCases\UpdateCustomerAddress\UpdateCustomerAddressCommand;
use Modules\Customer\Application\UseCases\UpdateCustomerDepartment\UpdateCustomerDepartmentCommand;
use Modules\Customer\Application\UseCases\UpdateCustomerPosition\UpdateCustomerPositionCommand;
use Modules\Customer\Application\UseCases\UpdateCustomerProfile\UpdateCustomerProfileCommand;
use Modules\Customer\Domain\Enums\ContactPointIdentifierKind;
use Modules\Customer\Domain\Enums\ContactPointScope;
use Modules\Customer\Domain\Enums\ContactPointStatus;
use Modules\Customer\Domain\Enums\ContactPointType;
use Modules\Customer\Domain\Enums\CreditRating;
use Modules\Customer\Domain\Enums\CustomerHistoryCategory;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class CustomerCommandMapper
{
    public static function create(AuthenticatedPrincipal $actor, array $input): CreateCustomerCommand
    {
        return new CreateCustomerCommand($actor, new CustomerDraftDto(
            firstName: $input['first_name'],
            familyName: $input['family_name'],
            displayName: $input['display_name'],
            kind: CustomerKind::from($input['kind']),
            phase: CustomerPhase::from($input['phase']),
            address: new CustomerAddressDto(
                countryCode: $input['country_code'],
                provinceId: isset($input['province_id']) ? (string) $input['province_id'] : null,
                cityId: isset($input['city_id']) ? (string) $input['city_id'] : null,
                foreignCity: $input['foreign_city'] ?? null,
                postalCode: $input['postal_code'] ?? null,
                addressText: $input['address_text'] ?? null,
            ),
            mobile: $input['mobile'],
            customerCode: $input['customer_code'] ?? null,
            industryId: isset($input['industry_id']) ? (string) $input['industry_id'] : null,
            assigneeId: isset($input['assignee_id']) ? (string) $input['assignee_id'] : null,
        ));
    }

    public static function addressListing(AuthenticatedPrincipal $actor, string $customerId): ListCustomerAddressesCommand
    {
        return new ListCustomerAddressesCommand($actor, $customerId);
    }

    public static function address(AuthenticatedPrincipal $actor, string $customerId, string $addressId): GetCustomerAddressCommand
    {
        return new GetCustomerAddressCommand($actor, $customerId, $addressId);
    }

    public static function addressDraft(AuthenticatedPrincipal $actor, string $customerId, array $input): CreateCustomerAddressCommand
    {
        return new CreateCustomerAddressCommand($actor, $customerId, new CustomerAddressDraftDto(
            countryCode: $input['country_code'],
            purpose: $input['purpose'],
            addressText: $input['address_text'],
            provinceId: isset($input['province_id']) ? (string) $input['province_id'] : null,
            cityId: isset($input['city_id']) ? (string) $input['city_id'] : null,
            foreignRegion: $input['foreign_region'] ?? null,
            foreignCity: $input['foreign_city'] ?? null,
            postalCode: $input['postal_code'] ?? null,
            plaque: $input['plaque'] ?? null,
            unit: $input['unit'] ?? null,
            latitude: self::coordinate($input['latitude'] ?? null),
            longitude: self::coordinate($input['longitude'] ?? null),
            isDefault: (bool) ($input['is_default'] ?? false),
        ));
    }

    public static function addressChanges(AuthenticatedPrincipal $actor, string $customerId, string $addressId, array $input): UpdateCustomerAddressCommand
    {
        // array_key_exists, not isset: an explicit null clears the field and must reach the handler.
        // Latitude and longitude share one flag, because the stored pin is set or cleared as a whole.
        $coordinatesSpecified = array_key_exists('latitude', $input) || array_key_exists('longitude', $input);

        return new UpdateCustomerAddressCommand($actor, $customerId, $addressId, new CustomerAddressChangesDto(
            countryCode: $input['country_code'] ?? null,
            purpose: $input['purpose'] ?? null,
            addressText: $input['address_text'] ?? null,
            provinceId: isset($input['province_id']) ? (string) $input['province_id'] : null,
            provinceSpecified: array_key_exists('province_id', $input),
            cityId: isset($input['city_id']) ? (string) $input['city_id'] : null,
            citySpecified: array_key_exists('city_id', $input),
            foreignRegion: $input['foreign_region'] ?? null,
            foreignRegionSpecified: array_key_exists('foreign_region', $input),
            foreignCity: $input['foreign_city'] ?? null,
            foreignCitySpecified: array_key_exists('foreign_city', $input),
            postalCode: $input['postal_code'] ?? null,
            postalCodeSpecified: array_key_exists('postal_code', $input),
            plaque: $input['plaque'] ?? null,
            plaqueSpecified: array_key_exists('plaque', $input),
            unit: $input['unit'] ?? null,
            unitSpecified: array_key_exists('unit', $input),
            latitude: self::coordinate($input['latitude'] ?? null),
            longitude: self::coordinate($input['longitude'] ?? null),
            coordinatesSpecified: $coordinatesSpecified,
            isDefault: array_key_exists('is_default', $input) ? (bool) $input['is_default'] : null,
        ));
    }

    public static function profile(AuthenticatedPrincipal $actor, string $customerId): GetCustomerProfileCommand
    {
        return new GetCustomerProfileCommand($actor, $customerId);
    }

    public static function profileChanges(AuthenticatedPrincipal $actor, string $customerId, array $input): UpdateCustomerProfileCommand
    {
        return new UpdateCustomerProfileCommand($actor, $customerId, new CustomerProfileChangesDto(
            kind: isset($input['kind']) ? CustomerKind::from($input['kind']) : null,
            phase: isset($input['phase']) ? CustomerPhase::from($input['phase']) : null,
            lifecycle: isset($input['lifecycle']) ? CustomerLifecycle::from($input['lifecycle']) : null,
            // array_key_exists, not isset: an explicit null clears the field and must reach the handler.
            displayName: $input['display_name'] ?? null,
            displayNameSpecified: array_key_exists('display_name', $input),
            customerCode: $input['customer_code'] ?? null,
            customerCodeSpecified: array_key_exists('customer_code', $input),
            assigneeId: isset($input['assignee_id']) ? (string) $input['assignee_id'] : null,
            assigneeSpecified: array_key_exists('assignee_id', $input),
            primaryIndustryId: isset($input['primary_industry_id']) ? (string) $input['primary_industry_id'] : null,
            primaryIndustrySpecified: array_key_exists('primary_industry_id', $input),
        ));
    }

    public static function extendedDetails(AuthenticatedPrincipal $actor, string $customerId): GetCustomerExtendedDetailsCommand
    {
        return new GetCustomerExtendedDetailsCommand($actor, $customerId);
    }

    public static function extendedDetailsInput(AuthenticatedPrincipal $actor, string $customerId, array $input): SaveCustomerExtendedDetailsCommand
    {
        return new SaveCustomerExtendedDetailsCommand($actor, $customerId, new CustomerExtendedDetailsDto(
            salutation: $input['salutation'] ?? null,
            birthDate: self::instant($input['birth_date'] ?? null),
            tradeName: $input['trade_name'] ?? null,
            legalForm: $input['legal_form'] ?? null,
            legalName: $input['legal_name'] ?? null,
            registrationNo: $input['registration_no'] ?? null,
            registrationDate: self::instant($input['registration_date'] ?? null),
            registrationPlace: $input['registration_place'] ?? null,
            needSummary: $input['need_summary'] ?? null,
            budget: isset($input['budget']) ? (int) $input['budget'] : null,
            budgetKnown: isset($input['budget_known']) ? (bool) $input['budget_known'] : null,
            authorityNote: $input['authority_note'] ?? null,
            needConfirmed: isset($input['need_confirmed']) ? (bool) $input['need_confirmed'] : null,
            timeframe: $input['timeframe'] ?? null,
            qualificationResult: $input['qualification_result'] ?? null,
        ));
    }

    public static function financialDetails(AuthenticatedPrincipal $actor, string $customerId): GetCustomerFinancialDetailsCommand
    {
        return new GetCustomerFinancialDetailsCommand($actor, $customerId);
    }

    public static function financialDetailsInput(AuthenticatedPrincipal $actor, string $customerId, array $input): SaveCustomerFinancialDetailsCommand
    {
        return new SaveCustomerFinancialDetailsCommand($actor, $customerId, new CustomerFinancialDetailsDto(
            // The request demands both, so neither can be missing by the time the command is built.
            financialReferenceDate: self::requiredInstant($input['financial_reference_date']),
            sourceNote: $input['source_note'],
            creditLimit: isset($input['credit_limit']) ? (int) $input['credit_limit'] : null,
            creditRating: isset($input['credit_rating']) ? CreditRating::from($input['credit_rating']) : null,
            settlementTerms: $input['settlement_terms'] ?? null,
            accountingCode: $input['accounting_code'] ?? null,
            accountingTitle: $input['accounting_title'] ?? null,
            revenue: isset($input['revenue']) ? (int) $input['revenue'] : null,
            receipts: isset($input['receipts']) ? (int) $input['receipts'] : null,
            directCost: isset($input['direct_cost']) ? (int) $input['direct_cost'] : null,
            balance: isset($input['balance']) ? (int) $input['balance'] : null,
        ));
    }

    public static function detail(AuthenticatedPrincipal $actor, string $customerId): GetCustomerDetailCommand
    {
        return new GetCustomerDetailCommand($actor, $customerId);
    }

    public static function listing(AuthenticatedPrincipal $actor, array $input): ListCustomersCommand
    {
        return new ListCustomersCommand($actor, new CustomerListFiltersDto(
            page: (int) ($input['page'] ?? 1),
            perPage: (int) ($input['per_page'] ?? 25),
            displayName: $input['display_name'] ?? null,
            customerCode: $input['customer_code'] ?? null,
            phase: isset($input['phase']) ? CustomerPhase::from($input['phase']) : null,
            kind: isset($input['kind']) ? CustomerKind::from($input['kind']) : null,
            lifecycle: isset($input['lifecycle']) ? CustomerLifecycle::from($input['lifecycle']) : null,
            assigneeId: isset($input['assignee_id']) ? (string) $input['assignee_id'] : null,
            updatedAt: self::date($input['updated_at'] ?? null),
            updatedAtFrom: self::date($input['updated_at_from'] ?? null),
            updatedAtTo: self::date($input['updated_at_to'] ?? null),
        ));
    }

    public static function orgStructure(AuthenticatedPrincipal $actor, string $customerId): GetCustomerOrgStructureCommand
    {
        return new GetCustomerOrgStructureCommand($actor, $customerId);
    }

    public static function departmentDraft(AuthenticatedPrincipal $actor, string $customerId, array $input): CreateCustomerDepartmentCommand
    {
        return new CreateCustomerDepartmentCommand($actor, $customerId, new CustomerDepartmentDraftDto(
            title: $input['title'],
            parentDepartmentId: isset($input['parent_department_id']) ? (string) $input['parent_department_id'] : null,
            costCenterCode: $input['cost_center_code'] ?? null,
        ));
    }

    public static function departmentChanges(AuthenticatedPrincipal $actor, string $customerId, string $departmentId, array $input): UpdateCustomerDepartmentCommand
    {
        return new UpdateCustomerDepartmentCommand($actor, $customerId, $departmentId, new CustomerDepartmentChangesDto(
            title: $input['title'] ?? null,
            // array_key_exists, not isset: an explicit null moves the node to the root and must reach the handler.
            parentDepartmentId: isset($input['parent_department_id']) ? (string) $input['parent_department_id'] : null,
            parentSpecified: array_key_exists('parent_department_id', $input),
            costCenterCode: $input['cost_center_code'] ?? null,
            costCenterSpecified: array_key_exists('cost_center_code', $input),
        ));
    }

    public static function positionDraft(AuthenticatedPrincipal $actor, string $customerId, string $departmentId, array $input): CreateCustomerPositionCommand
    {
        return new CreateCustomerPositionCommand($actor, $customerId, $departmentId, new CustomerPositionDraftDto(
            title: $input['title'],
            decisionLevel: $input['decision_level'] ?? null,
            delegationLimit: isset($input['delegation_limit']) ? (int) $input['delegation_limit'] : null,
        ));
    }

    public static function positionChanges(AuthenticatedPrincipal $actor, string $customerId, string $positionId, array $input): UpdateCustomerPositionCommand
    {
        return new UpdateCustomerPositionCommand($actor, $customerId, $positionId, new CustomerPositionChangesDto(
            title: $input['title'] ?? null,
            decisionLevel: $input['decision_level'] ?? null,
            decisionLevelSpecified: array_key_exists('decision_level', $input),
            delegationLimit: isset($input['delegation_limit']) ? (int) $input['delegation_limit'] : null,
            delegationLimitSpecified: array_key_exists('delegation_limit', $input),
        ));
    }

    public static function history(AuthenticatedPrincipal $actor, string $customerId): GetCustomerHistoryCommand
    {
        return new GetCustomerHistoryCommand($actor, $customerId);
    }

    public static function historyEntries(AuthenticatedPrincipal $actor, string $customerId, ?string $category, array $input): ListCustomerHistoryEntriesCommand
    {
        return new ListCustomerHistoryEntriesCommand(
            actor: $actor,
            customerId: $customerId,
            // No category is the continuous timeline across all six drawers.
            category: $category === null ? null : CustomerHistoryCategory::from($category),
            search: $input['search'] ?? null,
            page: (int) ($input['page'] ?? 1),
            perPage: (int) ($input['per_page'] ?? 25),
        );
    }

    public static function contactPointListing(AuthenticatedPrincipal $actor, string $customerId): ListCustomerContactPointsCommand
    {
        return new ListCustomerContactPointsCommand($actor, $customerId);
    }

    /** @param array{items: list<array<string, mixed>>} $input */
    public static function contactPointSet(AuthenticatedPrincipal $actor, string $customerId, array $input): SaveCustomerContactPointsCommand
    {
        return new SaveCustomerContactPointsCommand($actor, $customerId, array_map(
            static fn (array $item): CustomerContactPointDraftDto => new CustomerContactPointDraftDto(
                id: isset($item['id']) ? (string) $item['id'] : null,
                type: ContactPointType::from($item['type']),
                identifierKind: ContactPointIdentifierKind::from($item['identifier_kind']),
                value: $item['value'],
                scope: ContactPointScope::from($item['scope']),
                isDefault: (bool) ($item['is_default'] ?? false),
                status: ContactPointStatus::from($item['status'] ?? ContactPointStatus::ACTIVE->value),
                priority: isset($item['priority']) ? (int) $item['priority'] : null,
                subtype: $item['subtype'] ?? null,
                workContext: $item['work_context'] ?? null,
                relationshipId: isset($item['relationship_id']) ? (string) $item['relationship_id'] : null,
                addressId: isset($item['address_id']) ? (string) $item['address_id'] : null,
                verifiedManually: (bool) ($item['verified_manually'] ?? false),
            ),
            array_values($input['items']),
        ));
    }

    public static function industryListing(AuthenticatedPrincipal $actor, string $customerId): ListCustomerIndustriesCommand
    {
        return new ListCustomerIndustriesCommand($actor, $customerId);
    }

    /** @param array{items: list<array<string, mixed>>} $input */
    public static function industrySet(AuthenticatedPrincipal $actor, string $customerId, array $input): SaveCustomerIndustriesCommand
    {
        return new SaveCustomerIndustriesCommand($actor, $customerId, array_map(
            static fn (array $item): CustomerIndustryDraftDto => new CustomerIndustryDraftDto((string) $item['industry_id'], (bool) $item['is_primary']),
            array_values($input['items']),
        ));
    }

    /** @param array<string, mixed> $input */
    public static function duplicateLookup(AuthenticatedPrincipal $actor, array $input): FindCustomerDuplicatesCommand
    {
        return new FindCustomerDuplicatesCommand($actor, $input['mobile'] ?? null, $input['email'] ?? null);
    }

    public static function relationshipListing(AuthenticatedPrincipal $actor, string $customerId, bool $activeOnly): ListCustomerRelationshipsCommand
    {
        return new ListCustomerRelationshipsCommand($actor, $customerId, $activeOnly);
    }

    /** @param array<string, mixed> $input */
    public static function companyRelationship(AuthenticatedPrincipal $actor, string $customerId, array $input): CreateCompanyRelationshipCommand
    {
        $newPerson = $input['new_person'] ?? null;

        return new CreateCompanyRelationshipCommand($actor, $customerId, new CompanyRelationshipDraftDto(
            personCustomerId: isset($input['person_customer_id']) ? (string) $input['person_customer_id'] : null,
            newPerson: empty($newPerson) ? null : new NewPersonDto($newPerson['first_name'], $newPerson['family_name'], $newPerson['mobile']),
            positionId: isset($input['position_id']) ? (string) $input['position_id'] : null,
            roleTitle: $input['role_title'],
            decisionLevel: $input['decision_level'] ?? null,
            signingAuthority: $input['signing_authority'] ?? null,
            validFrom: self::date($input['valid_from'] ?? null),
            validTo: self::date($input['valid_to'] ?? null),
            isPrimary: (bool) ($input['is_primary'] ?? false),
            replacePrimary: (bool) ($input['replace_primary'] ?? false),
        ));
    }

    /** @param array<string, mixed> $input */
    public static function relationshipEnd(AuthenticatedPrincipal $actor, string $relationshipId, array $input): EndCustomerRelationshipCommand
    {
        return new EndCustomerRelationshipCommand($actor, $relationshipId, self::date($input['valid_to']));
    }

    /** @param array<string, mixed> $input */
    public static function leadConversion(AuthenticatedPrincipal $actor, string $customerId, array $input): ConvertLeadCommand
    {
        return new ConvertLeadCommand($actor, $customerId, new LeadConversionDto(
            firstName: $input['first_name'] ?? null,
            familyName: $input['family_name'] ?? null,
            displayName: $input['display_name'] ?? null,
            customerCode: $input['customer_code'] ?? null,
            mergeIntoCustomerId: isset($input['merge_into_customer_id']) ? (string) $input['merge_into_customer_id'] : null,
            confirmMerge: (bool) ($input['confirm_merge'] ?? false),
        ));
    }

    /** @param array<string, mixed> $input */
    public static function lifecycleChange(AuthenticatedPrincipal $actor, string $customerId, array $input): ChangeCustomerLifecycleCommand
    {
        return new ChangeCustomerLifecycleCommand($actor, $customerId, CustomerLifecycle::from($input['lifecycle']), $input['reason'] ?? null);
    }

    /** A unix timestamp in seconds; the epoch spelling makes the instant UTC whatever the server clock is. */
    private static function instant(int|string|null $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable('@'.$value);
    }

    /** The same instant where the caller has already proved the value is there. */
    private static function requiredInstant(int|string $value): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$value);
    }

    private static function date(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    /** A coordinate keeps the decimal spelling it arrived in, so the stored precision is never rounded. */
    private static function coordinate(float|int|string|null $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
