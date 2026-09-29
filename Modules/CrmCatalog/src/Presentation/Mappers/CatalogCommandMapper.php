<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Presentation\Mappers;

use Modules\CrmCatalog\Application\Dto\CatalogItemChangesDto;
use Modules\CrmCatalog\Application\Dto\CatalogItemDraftDto;
use Modules\CrmCatalog\Application\Dto\CatalogItemFiltersDto;
use Modules\CrmCatalog\Application\UseCases\CreateCatalogItem\CreateCatalogItemCommand;
use Modules\CrmCatalog\Application\UseCases\GetCatalogItem\GetCatalogItemCommand;
use Modules\CrmCatalog\Application\UseCases\ListCatalogCategories\ListCatalogCategoriesCommand;
use Modules\CrmCatalog\Application\UseCases\ListCatalogItems\ListCatalogItemsCommand;
use Modules\CrmCatalog\Application\UseCases\ListCatalogPersonas\ListCatalogPersonasCommand;
use Modules\CrmCatalog\Application\UseCases\ListCatalogSalesModels\ListCatalogSalesModelsCommand;
use Modules\CrmCatalog\Application\UseCases\UpdateCatalogItem\UpdateCatalogItemCommand;
use Modules\CrmCatalog\Domain\Enums\CatalogItemKind;
use Modules\CrmCatalog\Domain\Enums\CatalogItemStatus;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class CatalogCommandMapper
{
    public static function listing(AuthenticatedPrincipal $actor, array $input): ListCatalogItemsCommand
    {
        return new ListCatalogItemsCommand($actor, new CatalogItemFiltersDto(
            kind: isset($input['kind']) ? CatalogItemKind::from($input['kind']) : null,
            status: isset($input['status']) ? CatalogItemStatus::from($input['status']) : null,
            categoryId: isset($input['category_id']) ? (string) $input['category_id'] : null,
            search: isset($input['q']) && trim($input['q']) !== '' ? trim($input['q']) : null,
        ));
    }

    public static function item(AuthenticatedPrincipal $actor, string $catalogItemId): GetCatalogItemCommand
    {
        return new GetCatalogItemCommand($actor, $catalogItemId);
    }

    public static function draft(AuthenticatedPrincipal $actor, array $input): CreateCatalogItemCommand
    {
        return new CreateCatalogItemCommand($actor, new CatalogItemDraftDto(
            code: $input['code'],
            title: $input['title'],
            kind: CatalogItemKind::from($input['kind']),
            status: isset($input['status']) ? CatalogItemStatus::from($input['status']) : CatalogItemStatus::ACTIVE,
            categoryId: isset($input['category_id']) ? (string) $input['category_id'] : null,
            buyerPersonaId: isset($input['buyer_persona_id']) ? (string) $input['buyer_persona_id'] : null,
            salesModelId: isset($input['sales_model_id']) ? (string) $input['sales_model_id'] : null,
            description: $input['description'] ?? null,
            deliveryTerms: $input['delivery_terms'] ?? null,
            leadTime: $input['lead_time'] ?? null,
            afterSalesPolicy: $input['after_sales_policy'] ?? null,
            slaDescription: $input['sla_description'] ?? null,
            warrantyDescription: $input['warranty_description'] ?? null,
            legalNotes: $input['legal_notes'] ?? null,
            industryIds: array_map('strval', $input['industry_ids'] ?? []),
        ));
    }

    public static function changes(AuthenticatedPrincipal $actor, string $catalogItemId, array $input): UpdateCatalogItemCommand
    {
        // array_key_exists, not isset: an explicit null clears the field and must reach the handler.
        return new UpdateCatalogItemCommand($actor, $catalogItemId, new CatalogItemChangesDto(
            code: $input['code'] ?? null,
            title: $input['title'] ?? null,
            kind: isset($input['kind']) ? CatalogItemKind::from($input['kind']) : null,
            status: isset($input['status']) ? CatalogItemStatus::from($input['status']) : null,
            categoryId: isset($input['category_id']) ? (string) $input['category_id'] : null,
            categorySpecified: array_key_exists('category_id', $input),
            buyerPersonaId: isset($input['buyer_persona_id']) ? (string) $input['buyer_persona_id'] : null,
            buyerPersonaSpecified: array_key_exists('buyer_persona_id', $input),
            salesModelId: isset($input['sales_model_id']) ? (string) $input['sales_model_id'] : null,
            salesModelSpecified: array_key_exists('sales_model_id', $input),
            description: $input['description'] ?? null,
            descriptionSpecified: array_key_exists('description', $input),
            deliveryTerms: $input['delivery_terms'] ?? null,
            deliveryTermsSpecified: array_key_exists('delivery_terms', $input),
            leadTime: $input['lead_time'] ?? null,
            leadTimeSpecified: array_key_exists('lead_time', $input),
            afterSalesPolicy: $input['after_sales_policy'] ?? null,
            afterSalesPolicySpecified: array_key_exists('after_sales_policy', $input),
            slaDescription: $input['sla_description'] ?? null,
            slaDescriptionSpecified: array_key_exists('sla_description', $input),
            warrantyDescription: $input['warranty_description'] ?? null,
            warrantyDescriptionSpecified: array_key_exists('warranty_description', $input),
            legalNotes: $input['legal_notes'] ?? null,
            legalNotesSpecified: array_key_exists('legal_notes', $input),
            industryIds: array_key_exists('industry_ids', $input) ? array_map('strval', $input['industry_ids']) : null,
        ));
    }

    public static function categories(AuthenticatedPrincipal $actor, array $input): ListCatalogCategoriesCommand
    {
        return new ListCatalogCategoriesCommand($actor, (bool) ($input['active'] ?? false));
    }

    public static function personas(AuthenticatedPrincipal $actor, array $input): ListCatalogPersonasCommand
    {
        return new ListCatalogPersonasCommand($actor, (bool) ($input['active'] ?? false));
    }

    public static function salesModels(AuthenticatedPrincipal $actor, array $input): ListCatalogSalesModelsCommand
    {
        return new ListCatalogSalesModelsCommand($actor, (bool) ($input['active'] ?? false));
    }
}
