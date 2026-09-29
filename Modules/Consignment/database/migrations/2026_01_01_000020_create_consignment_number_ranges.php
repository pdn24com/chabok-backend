<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_number_ranges', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('title', 200);
            $table->string('numeric_prefix', 27);
            $table->unsignedTinyInteger('total_length');
            $table->unsignedTinyInteger('serial_width');
            $table->string('serial_start', 28);
            $table->string('serial_end', 28);
            $table->string('next_serial', 28)->nullable();
            $table->string('first_number', 28);
            $table->string('last_number', 28);
            $table->enum('status', ['AVAILABLE', 'EXHAUSTED', 'DISABLED']);
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unsignedInteger('disabled_by')->nullable();
            $table->timestamp('disabled_at', 6)->nullable();
            $table->timestamp('exhausted_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'number_ranges_hq_range_unique');
            $table->index(['created_by'], 'consignment_number_ranges_created_by_foreign');
            $table->index(['disabled_by'], 'consignment_number_ranges_disabled_by_foreign');
            $table->index(['hq_id', 'status', 'created_at', 'id'], 'number_ranges_fifo_index');
            $table->index(['total_length', 'first_number', 'last_number'], 'number_ranges_overlap_index');
            $table->foreign(['created_by'], 'consignment_number_ranges_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['disabled_by'], 'consignment_number_ranges_disabled_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id'], 'consignment_number_ranges_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_number_ranges');
    }
};
