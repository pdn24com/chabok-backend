<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Iam\Application\Services\UserIdentifierResolver;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Tests\TestCase;

final class UserIdentifierNativeTest extends TestCase
{
    public function test_identifier_lookup_keeps_global_cross_field_matching_and_never_selects_a_user_for_empty_input(): void
    {
        config(['database.connections.user_identifier' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('user_identifier');
        DB::connection()->getPdo()->sqliteCreateCollation('utf8mb4_bin', strcmp(...));
        (require glob(base_path('Modules/Iam/database/migrations/*_create_users.php'))[0])->up();
        UserRecord::query()->forceCreate(['user_id' => '175716279', 'hq_id' => '245213294', 'normalized_username' => 'first',
            'normalized_email' => 'person@example.test', 'normalized_mobile' => '+989123456789',
            'first_name' => 'First', 'last_name' => 'User', 'display_name' => 'First User', 'status' => 'ACTIVE']);
        UserRecord::query()->forceCreate(['user_id' => '261199080', 'hq_id' => '227711138', 'normalized_username' => '0',
            'first_name' => 'Zero', 'last_name' => 'User', 'display_name' => 'Zero User', 'status' => 'ACTIVE']);
        $query = $this->app->make(UserIdentifierResolver::class);
        foreach ([' FIRST ', 'Person@Example.Test', '0098 912 345 6789'] as $identifier) {
            self::assertSame('175716279', $query->findByIdentifier($identifier)->user_id);
        }
        self::assertSame('261199080', $query->findByIdentifier('0')->user_id);
        self::assertTrue($query->identifiersExist(['0']));
        self::assertTrue($query->identifiersExist([null, 'person@example.test']));
        self::assertTrue($query->identifiersExist(['+989123456789']));
        self::assertNull($query->findByIdentifier('unknown'));
        self::assertFalse($query->identifiersExist(['unused']));
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            self::assertNull($query->findByIdentifier(''));
            self::assertNull($query->findByIdentifier('   '));
            self::assertFalse($query->identifiersExist([null, '']));
            self::assertSame([], DB::connection()->getQueryLog());
        } finally {
            DB::connection()->disableQueryLog();
        }
    }
}
