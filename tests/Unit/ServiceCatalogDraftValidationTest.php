<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\ServiceCatalog\Infrastructure\Http\ServiceCatalogController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ServiceCatalogDraftValidationTest extends TestCase
{
    public function test_non_offering_definition_is_optional_as_declared_by_openapi(): void
    {
        $reflection = new ReflectionClass(ServiceCatalogController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('draftRules');

        foreach (['service-types', 'shipping-methods', 'options'] as $resource) {
            /** @var array<string, mixed> $rules */
            $rules = $method->invoke($controller, $resource, true);

            self::assertSame(['sometimes', 'array'], $rules['definition']);
        }
    }
}
