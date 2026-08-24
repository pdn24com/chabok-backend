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
        Schema::table('operational_exception_cases', function (Blueprint $table): void {
            $table->char('manifest_id', 36)->nullable()->after('exception_type');
            $table->unsignedInteger('submission_sequence')->default(1)->after('manifest_id');
            $table->char('active_pending_slot', 64)->nullable()->unique()->after('version');
            $table->foreign(['hq_id', 'manifest_id'], 'exception_cases_manifest_fk')->references(['hq_id', 'manifest_id'])->on('manifests')->restrictOnDelete();
            $table->unique(['manifest_id', 'submission_sequence'], 'exception_cases_manifest_attempt_unique');
            $table->index(['hq_id', 'manifest_id', 'case_status'], 'exception_cases_manifest_status_index');
        });
        Schema::table('operational_exception_history', function (Blueprint $table): void {
            $table->unsignedInteger('manifest_version')->nullable()->after('safe_note');
            $table->unsignedInteger('exception_version')->nullable()->after('manifest_version');
        });
    }

    public function down(): void
    {
        Schema::table('operational_exception_history', function (Blueprint $table): void {
            $table->dropColumn(['manifest_version', 'exception_version']);
        });
        Schema::table('operational_exception_cases', function (Blueprint $table): void {
            $table->dropForeign('exception_cases_manifest_fk');
            $table->dropUnique('exception_cases_manifest_attempt_unique');
            $table->dropUnique('operational_exception_cases_active_pending_slot_unique');
            $table->dropIndex('exception_cases_manifest_status_index');
            $table->dropColumn(['manifest_id', 'submission_sequence', 'active_pending_slot']);
        });
    }
};
