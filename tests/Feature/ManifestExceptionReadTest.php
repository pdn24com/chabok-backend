<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manifest\Application\Serialization\ManifestWorkflowDocument;
use Modules\Operations\Infrastructure\Repositories\EloquentManifestExceptionAccess;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ManifestExceptionReadTest extends TestCase
{
    public function test_exception_history_and_reviewers_are_batched_and_keep_event_timestamp(): void
    {
        config(['database.connections.exception_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('exception_read_test');
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('display_name');
            $table->string('created_at');
        });
        foreach (['operational_exception_cases', 'operational_exception_history'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        RecordFixtureQuery::table('users')->insert(['user_id' => '5212567', 'display_name' => 'Reviewer', 'created_at' => '2020-01-01 00:00:00']);
        $access = new EloquentManifestExceptionAccess;
        $projection = $this->app->make(ManifestWorkflowDocument::class);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            RecordFixtureQuery::table('operational_exception_cases')->insert(['exception_case_id' => \Tests\Support\FixtureId::from('case-'.$index), 'hq_id' => '245213294', 'exception_type' => 'NOK', 'manifest_id' => '5978816', 'consignment_id' => '92337101',
                'submission_sequence' => $index, 'driver_id' => '189656963', 'submitted_by' => '5212567', 'reviewed_by' => '5212567', 'reason_code' => 'ABSENT', 'description' => 'Recipient unavailable', 'case_status' => 'REJECTED', 'created_at' => '2026-09-23 09:00:00']);
            RecordFixtureQuery::table('operational_exception_history')->insert(['exception_history_id' => \Tests\Support\FixtureId::from('history-'.$index), 'hq_id' => '245213294', 'exception_case_id' => \Tests\Support\FixtureId::from('case-'.$index), 'action' => 'REJECTED',
                'actor_id' => '5212567', 'manifest_version' => 4, 'exception_version' => 2, 'created_at' => '2026-09-23 10:00:00']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $cases = $access->exceptionAttempts('245213294', '5978816');
                $data = $cases->map($projection->caseResource(...))->all();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $data);
            self::assertSame('Reviewer', $data[0]['history'][0]['actor']['display_name']);
            self::assertSame('2026-09-23 10:00:00', $data[0]['history'][0]['occurred_at']);
            self::assertSame(4, $data[0]['history'][0]['manifest_version']);
        }
        self::assertSame([5, 5], $counts);
        self::assertCount(0, $access->exceptionAttempts('106329882', '5978816'));
    }
}
