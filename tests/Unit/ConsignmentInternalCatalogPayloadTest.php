<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Consignment\Infrastructure\Http\ConsignmentController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ConsignmentInternalCatalogPayloadTest extends TestCase
{
    public function test_internal_catalog_fields_are_allowed_in_consignment_drafts(): void
    {
        $reflection = new ReflectionClass(ConsignmentController::class);
        $allowedFields = $reflection->getConstant('DRAFT_FIELDS');

        self::assertIsArray($allowedFields);
        self::assertContains('service_offering_id', $allowedFields);
        self::assertContains('service_offering_version_id', $allowedFields);
        self::assertContains('selected_option_version_ids', $allowedFields);
        self::assertContains('pickup_service_date', $allowedFields);
        self::assertContains('pickup_window_code', $allowedFields);
        self::assertContains('delivery_window_code', $allowedFields);
    }
}
