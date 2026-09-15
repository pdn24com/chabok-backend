<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up(): void {
  Schema::table('commitment_schedule_versions', fn(Blueprint $table) => $table->json('commitment_policy')->nullable());
  DB::unprepared("CREATE TRIGGER commitment_policy_immutable BEFORE UPDATE ON commitment_schedule_versions FOR EACH ROW BEGIN IF OLD.status IN ('PUBLISHED','SUPERSEDED','ARCHIVED') AND NOT (NEW.commitment_policy <=> OLD.commitment_policy) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable published commitment policy'; END IF; END");
 }
 public function down(): void { DB::unprepared('DROP TRIGGER IF EXISTS commitment_policy_immutable'); Schema::table('commitment_schedule_versions', fn(Blueprint $table) => $table->dropColumn('commitment_policy')); }
};
