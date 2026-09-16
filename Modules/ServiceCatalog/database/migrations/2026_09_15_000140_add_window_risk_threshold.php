<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('commitment_schedule_windows', fn (Blueprint $table) => $table->unsignedInteger('risk_threshold_minutes')->default(120)); }
 public function down(): void { Schema::table('commitment_schedule_windows', fn (Blueprint $table) => $table->dropColumn('risk_threshold_minutes')); }
};
