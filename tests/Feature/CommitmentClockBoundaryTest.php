<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Mockery;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentClockInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Dto\FrozenDeliveryCommitmentDto;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Application\Services\FrozenCommitmentCompletion;
use Modules\ServiceCatalog\Application\Services\SchedulePolicy;
use Modules\ServiceCatalog\Application\Services\SchedulePolicyResolver;
use Modules\ServiceCatalog\Domain\Enums\CommitmentFailure;
use Modules\ServiceCatalog\Domain\Exceptions\CommitmentRuleViolation;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Presentation\Http\Resources\OfferingCommitmentResource;
use Tests\TestCase;

final class CommitmentClockBoundaryTest extends TestCase
{
    public function test_runtime_uses_one_injected_acceptance_time_and_frozen_completion_keeps_its_policy(): void
    {
        $now = new DateTimeImmutable('2026-09-24T01:02:03.123456Z');
        $clock = Mockery::mock(ClockInterface::class);
        $clock->shouldReceive('now')->once()->andReturn($now);
        $zones = Mockery::mock(CommitmentZoneResolverInterface::class);
        $resolver = new SchedulePolicyResolver($this->app->make(CommitmentClockInterface::class), $zones, $clock);
        $policy = SchedulePolicy::fromBinding(['pickup_mode' => 'NONE', 'delivery_mode' => 'COMPUTED',
            'duration_anchor' => 'PICKUP_COMPLETED', 'duration_value' => 24, 'duration_unit' => 'HOUR']);
        $version = new CommitmentScheduleVersionRecord;
        $version->forceFill(['commitment_schedule_version_id' => '100081670', 'timezone' => 'Asia/Tehran', 'commitment_policy' => $policy]);
        $version->setRelation('windows', new Collection);
        $snapshot = (new OfferingCommitmentResource($resolver->resolvePolicy($version, OfferingSelectionInput::fromArray([]), true, '245213294')))->resolve();
        self::assertSame('2026-09-24T01:02:03.123456Z', $snapshot['accepted_at']);
        self::assertSame($policy['delivery'], $snapshot['delivery']['policy']);
        self::assertTrue($snapshot['delivery']['awaiting_operation']);
        self::assertNull($snapshot['delivery']['computed_at']);
        $changed = $policy;
        $changed['delivery']['duration_value'] = 72;
        $version->commitment_policy = $changed;
        $result = $this->app->make(FrozenCommitmentCompletion::class)->pickupCompleted(FrozenDeliveryCommitmentDto::fromSnapshot($snapshot), new DateTimeImmutable('2026-09-25T10:11:12.654321Z'));
        self::assertSame('2026-09-26T10:11:12.654321Z', $result->computedAt->toISOString());
        self::assertSame(24, $result->policy->durationValue);
        self::assertFalse($result->awaitingOperation);
        self::assertNull(FrozenDeliveryCommitmentDto::fromSnapshot([]));
    }

    public function test_http_preserves_validation_status_reason_and_localized_message(): void
    {
        foreach (CommitmentFailure::cases() as $failure) {
            Route::get('/api/clock-test/'.$failure->value, static fn () => throw new CommitmentRuleViolation($failure));
            $this->getJson('/api/clock-test/'.$failure->value)->assertStatus(422)
                ->assertJsonPath('error_code', 'VALIDATION_ERROR')
                ->assertJsonPath('details.reason_code', $failure->value);
        }
    }
}
