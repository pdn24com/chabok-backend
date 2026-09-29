<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Pricing\Application\Dto\PricingVersionPeriodDto;
use Modules\Pricing\Application\Services\PricingVersionGuard;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Enums\PricingValidationCode;
use Modules\Pricing\Domain\Exceptions\InvalidTariffMatrix;
use Modules\Pricing\Domain\Exceptions\PricingValidationFailed;
use Modules\Pricing\Domain\ValueObjects\PricingValidationIssue;
use Modules\Pricing\Domain\ValueObjects\PricingValidationResult;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Modules\Pricing\Presentation\Http\Resources\PricingValidationResource;
use Tests\TestCase;

final class PricingValidationTest extends TestCase
{
    public function test_typed_validation_failure_preserves_the_http_error_details(): void
    {
        $result = new PricingValidationResult([
            new PricingValidationIssue(PricingValidationCode::VALID_FROM_REQUIRED, 'valid_from'),
            new PricingValidationIssue(PricingValidationCode::SERVICE_DEPENDENCY_INVALID, 'service_tariff_family_ids', 'Unavailable service tariff'),
        ]);
        Route::get('/api/v1/refactor-pricing-validation', fn () => throw new PricingValidationFailed($result, 'Pricing validation failed.'));
        $response = $this->getJson('/api/v1/refactor-pricing-validation')
            ->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonPath('details.valid', false)
            ->assertJsonPath('details.errors.0.code', 'PRICING_VALID_FROM_REQUIRED')
            ->assertJsonPath('details.errors.1.message', 'Unavailable service tariff');
        self::assertArrayNotHasKey('message', $response->json('details.errors.0'));
        self::assertSame(['valid' => true, 'errors' => []], (new PricingValidationResource(new PricingValidationResult([])))->resolve());
    }

    public function test_matrix_failure_preserves_its_original_http_details_shape(): void
    {
        $validation = new PricingValidationResult([new PricingValidationIssue(PricingValidationCode::SERVICE_COMPOSITION_INVALID, 'rules')]);
        Route::get('/api/v1/refactor-matrix-validation', fn () => throw new InvalidTariffMatrix($validation, 'Invalid matrix.'));
        $response = $this->getJson('/api/v1/refactor-matrix-validation')->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
        self::assertSame(['errors' => [['code' => 'PRICING_SERVICE_COMPOSITION_INVALID', 'field' => 'rules']]], $response->json('details'));
    }

    public function test_version_overlap_keeps_microseconds_and_half_open_boundaries(): void
    {
        config(['database.connections.pricing_period_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('pricing_period_test');
        $migration = require glob(base_path('Modules/Pricing/database/migrations/*_create_tariff_versions.php'))[0];
        $migration->up();
        TariffVersionRecord::query()->insert([
            'tariff_version_id' => '184870379', 'tariff_family_id' => '221554026', 'version_number' => 2,
            'status' => 'PUBLISHED', 'valid_from' => '2026-09-23 12:00:00.200000', 'valid_to' => '2026-09-23 12:00:00.600000', 'created_by' => '5212567',
        ]);
        $guard = $this->app->make(PricingVersionGuard::class);
        self::assertFalse($guard->hasVersionOverlap(PricingResource::Tariffs, $this->period('2026-09-23T12:00:00.600000Z', '2026-09-23T12:00:00.900000Z')));
        self::assertTrue($guard->hasVersionOverlap(PricingResource::Tariffs, $this->period('2026-09-23T12:00:00.100000Z', '2026-09-23T12:00:00.300000Z')));
        self::assertTrue($guard->hasVersionOverlap(PricingResource::Tariffs, $this->period('2026-09-23T15:30:00.500000+03:30', null)));
        self::assertFalse($guard->hasVersionOverlap(PricingResource::Tariffs, new PricingVersionPeriodDto('125058276', '221554026', 3, new DateTimeImmutable('2026-09-23T12:00:00Z'), null)));
        self::assertFalse($guard->hasVersionOverlap(PricingResource::Tariffs, new PricingVersionPeriodDto('125058276', '221554026', 1, null, null)));
    }

    private function period(string $from, ?string $to): PricingVersionPeriodDto
    {
        return new PricingVersionPeriodDto('125058276', '221554026', 1, new DateTimeImmutable($from), $to === null ? null : new DateTimeImmutable($to));
    }
}
