<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitment_schedule_windows', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('commitment_schedule_version_id');
            $table->string('window_code', 80);
            $table->enum('window_type', ['PICKUP', 'DELIVERY']);
            $table->string('label_fa', 200);
            $table->time('start_time', 0);
            $table->time('end_time', 0);
            $table->time('booking_cutoff_time', 0);
            $table->json('applicable_weekdays');
            $table->smallInteger('day_offset')->default('0');
            $table->boolean('active')->default('1');
            $table->unsignedInteger('risk_threshold_minutes')->default('120');
            $table->unique(['commitment_schedule_version_id', 'window_code'], 'commitment_schedule_window_code_unique');
            $table->index(['commitment_schedule_version_id', 'window_type', 'active'], 'commitment_schedule_window_lookup_index');
            $table->foreign(['commitment_schedule_version_id'], 'commitment_schedule_windows_version_fk')->references(['id'])->on('commitment_schedule_versions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_schedule_windows');
    }
};
