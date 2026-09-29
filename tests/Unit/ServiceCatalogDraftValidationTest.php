<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\ServiceCatalog\Presentation\Http\Requests\CatalogRequestRules;
use PHPUnit\Framework\TestCase;

final class ServiceCatalogDraftValidationTest extends TestCase
{
    public function test_non_offering_definition_is_optional_as_declared_by_openapi(): void
    {
        foreach (['service-types', 'shipping-methods', 'options'] as $resource) {
            /** @var array<string, mixed> $rules */
            $rules = CatalogRequestRules::draftRules($resource, true);
            self::assertSame(['sometimes', 'array'], $rules['definition']);
        }
    }
}
