<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(
            <<<'SQL'
            CREATE TRIGGER audit_events_prevent_update
            BEFORE UPDATE ON audit_events
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'
            SQL,
        );
        DB::unprepared(
            <<<'SQL'
            CREATE TRIGGER audit_events_prevent_delete
            BEFORE DELETE ON audit_events
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'
            SQL,
        );
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_prevent_update');
    }
};
