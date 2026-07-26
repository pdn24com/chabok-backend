<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE outbox_events MODIFY publication_state ENUM('PENDING','PUBLISHED','FAILED','DEAD_LETTER') NOT NULL DEFAULT 'PENDING'",
        );
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->char('claim_token', 36)->nullable()->unique()->after('publication_state');
            $table->string('claimed_by', 120)->nullable()->after('claim_token');
            $table->timestamp('claimed_at', 6)->nullable()->after('claimed_by');
            $table->timestamp('last_attempt_at', 6)->nullable()->after('attempts');
            $table->timestamp('dead_lettered_at', 6)->nullable()->after('published_at');
            $table->index(
                ['publication_state', 'claimed_at', 'next_attempt_at'],
                'outbox_claimable_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('outbox_events', function (Blueprint $table): void {
            $table->dropIndex('outbox_claimable_index');
            $table->dropColumn([
                'claim_token', 'claimed_by', 'claimed_at',
                'last_attempt_at', 'dead_lettered_at',
            ]);
        });
        DB::statement(
            "ALTER TABLE outbox_events MODIFY publication_state ENUM('PENDING','PUBLISHED','FAILED') NOT NULL DEFAULT 'PENDING'",
        );
    }
};
