<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote\AcceptConsignmentPricingQuoteCommand;
use Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote\AcceptConsignmentPricingQuoteHandler;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\ServiceCatalog\Application\Contracts\QuoteCatalogGuardInterface;
use Modules\ServiceCatalog\Application\Dto\QuoteCatalogReferencesDto;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class PricingAcceptanceNativeTest extends TestCase
{
    public function test_acceptance_copies_authoritative_lines_in_bounded_queries_and_rejects_replay(): void
    {
        $handler = $this->app->make(AcceptConsignmentPricingQuoteHandler::class);
        $counts = [];
        foreach ([1, 40, 101] as $count) {
            $quote = \Tests\Support\FixtureId::from('quote-'.$count);
            $this->quote($quote, $count);
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $snapshotId = DB::transaction(fn () => $handler->handle(new AcceptConsignmentPricingQuoteCommand('245213294', 'consignment-'.$count, '84712523', 2, $quote)));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            $snapshot = RecordFixtureQuery::table('pricing_snapshots')->where('pricing_snapshot_id', $snapshotId)->sole();
            self::assertSame($quote, $snapshot->quote_id);
            self::assertSame($count * 100, $snapshot->total_amount);
            self::assertSame('consignment:consignment-'.$count.':2', $snapshot->acceptance_idempotency_key);
            self::assertSame('ACCEPTED', PricingQuoteRecord::query()->where('quote_id', $quote)->sole()->status);
            $source = RecordFixtureQuery::table('pricing_quote_lines')->where('quote_id', $quote)->orderBy('line_number')->get();
            $accepted = RecordFixtureQuery::table('pricing_charge_lines')->where('pricing_snapshot_id', $snapshotId)->orderBy('line_number')->get();
            self::assertCount($count, $source);
            self::assertCount($count, $accepted);
            foreach ($source as $index => $line) {
                $original = (array) $line;
                $copy = (array) $accepted[$index];
                self::assertNotSame($original['quote_line_id'], $copy['charge_line_id']);
                unset($original['id'], $original['quote_line_id'], $original['quote_id'], $copy['id'], $copy['charge_line_id'], $copy['pricing_snapshot_id']);
                self::assertSame($original, $copy);
            }
            try {
                DB::transaction(fn () => $handler->handle(new AcceptConsignmentPricingQuoteCommand('245213294', 'consignment-'.$count, '84712523', 3, $quote)));
                self::fail('An accepted quote cannot be accepted again.');
            } catch (ApiException $error) {
                self::assertSame(422, $error->httpStatus);
            }
        }
        self::assertSame([5, 5, 6], $counts);
        self::assertSame(3, RecordFixtureQuery::table('pricing_snapshots')->count());
    }

    public function test_foreign_expired_and_failed_line_copies_cannot_leave_partial_acceptance(): void
    {
        $this->quote('103969350', 2);
        $handler = $this->app->make(AcceptConsignmentPricingQuoteHandler::class);
        foreach (['106329882', '262557346'] as $failure) {
            if ($failure === '262557346') {
                RecordFixtureQuery::table('pricing_quotes')->update(['expires_at' => '2026-09-23 23:59:59']);
            }
            try {
                DB::transaction(fn () => $handler->handle(new AcceptConsignmentPricingQuoteCommand($failure === '106329882' ? '227711138' : '245213294', '92337101', '84712523', 1, '103969350')));
                self::fail('Invalid quote must not be accepted.');
            } catch (ApiException $error) {
                self::assertSame(422, $error->httpStatus);
                self::assertSame(0, RecordFixtureQuery::table('pricing_snapshots')->count());
            }
        }
        RecordFixtureQuery::table('pricing_quotes')->update(['expires_at' => '2026-09-25 00:00:00']);
        DB::unprepared("CREATE TRIGGER reject_charge_line BEFORE INSERT ON pricing_charge_lines BEGIN SELECT RAISE(ABORT, 'Simulated line insert failure'); END");
        $handler = $this->app->make(AcceptConsignmentPricingQuoteHandler::class);
        try {
            DB::transaction(fn () => $handler->handle(new AcceptConsignmentPricingQuoteCommand('245213294', '92337101', '84712523', 1, '103969350')));
            self::fail('A failed line insert must roll back its snapshot.');
        } catch (QueryException) {
            self::assertSame(0, RecordFixtureQuery::table('pricing_snapshots')->count());
            self::assertSame(0, RecordFixtureQuery::table('pricing_charge_lines')->count());
            self::assertSame('OFFERED', PricingQuoteRecord::query()->sole()->status);
            self::assertSame(2, RecordFixtureQuery::table('pricing_quote_lines')->count());
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.acceptance_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('acceptance_test');
        foreach (['pricing_quotes', 'pricing_quote_lines', 'pricing_snapshots', 'pricing_charge_lines'] as $table) {
            (require glob(base_path('Modules/Pricing/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $clock = Mockery::mock(ClockInterface::class);
        $clock->shouldReceive('now')->andReturn(new DateTimeImmutable('2026-09-24T00:00:00Z'));
        $guard = Mockery::mock(QuoteCatalogGuardInterface::class);
        $guard->shouldReceive('assertQuoteCurrent')->with(Mockery::on(static fn ($references): bool => $references instanceof QuoteCatalogReferencesDto && $references->hqId === '245213294' && $references->offeringVersionId === '91125876'))->andReturnNull();
        $this->app->instance(ClockInterface::class, $clock);
        $this->app->instance(QuoteCatalogGuardInterface::class, $guard);
    }

    private function quote(string $id, int $lines): void
    {
        RecordFixtureQuery::table('pricing_quotes')->insert([
            'quote_id' => $id, 'hq_id' => '245213294', 'requested_by' => '84712523', 'purpose' => 'SALES',
            'tariff_version_id' => '32921751', 'zone_set_version_id' => '146962870', 'service_offering_id' => '90771604',
            'service_offering_version_id' => '91125876', 'origin_zone_id' => '25296341', 'destination_zone_id' => '190608731',
            'currency' => 'IRR', 'subtotal_amount' => $lines * 100, 'discount_amount' => 0, 'tax_amount' => 0, 'total_amount' => $lines * 100,
            'normalized_input' => '{}', 'resolution_evidence' => '{}', 'warnings' => '[]', 'input_fingerprint' => str_repeat('212432914', 64),
            'result_fingerprint' => str_repeat('65158786', 64), 'idempotency_key' => $id, 'calculated_at' => '2026-09-24 00:00:00', 'expires_at' => '2026-09-25 00:00:00',
        ]);
        for ($number = 1; $number <= $lines; $number++) {
            RecordFixtureQuery::table('pricing_quote_lines')->insert([
                'quote_line_id' => \Tests\Support\FixtureId::from($id.'-line-'.$number), 'quote_id' => $id, 'line_number' => $number,
                'charge_type_id' => '158632188', 'rate_rule_id' => '121018698', 'charge_type_code' => 'FREIGHT', 'title' => 'حمل',
                'calculation_method' => 'FIXED', 'basis' => 'PARCEL', 'quantity' => '1.2500', 'unit_rate' => '80.000000',
                'amount' => 100, 'accounting_mapping_key' => 'freight', 'explanation' => '{"weight":"1.2500"}',
            ]);
        }
    }
}
