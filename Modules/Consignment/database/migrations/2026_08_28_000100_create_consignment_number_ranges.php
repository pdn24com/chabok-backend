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
        Schema::create('consignment_number_range_registry', function (Blueprint $table): void {
            $table->string('registry_key', 32)->primary();
            $table->timestamp('updated_at', 6)->useCurrent();
        });
        DB::table('consignment_number_range_registry')->insert(['registry_key' => 'GLOBAL', 'updated_at' => now()]);

        Schema::create('consignment_number_ranges', function (Blueprint $table): void {
            $table->char('range_id', 36)->primary();
            $table->char('hq_id', 36);
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
            $table->char('created_by', 36);
            $table->timestamps(6);
            $table->char('disabled_by', 36)->nullable();
            $table->timestamp('disabled_at', 6)->nullable();
            $table->timestamp('exhausted_at', 6)->nullable();
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('disabled_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique(['hq_id', 'range_id'], 'number_ranges_hq_range_unique');
            $table->index(['hq_id', 'status', 'created_at', 'range_id'], 'number_ranges_fifo_index');
            $table->index(['total_length', 'first_number', 'last_number'], 'number_ranges_overlap_index');
        });

        Schema::create('consignment_number_allocations', function (Blueprint $table): void {
            $table->char('allocation_id', 36)->primary();
            $table->char('hq_id', 36);
            $table->char('range_id', 36);
            $table->char('consignment_id', 36);
            $table->string('consignment_number', 28)->unique();
            $table->char('allocated_by', 36);
            $table->timestamp('allocated_at', 6)->useCurrent();
            $table->char('correlation_id', 36);
            $table->char('idempotency_key_hash', 64)->nullable();
            $table->foreign('hq_id')->references('hq_id')->on('hq_tenants')->restrictOnDelete();
            $table->foreign(['hq_id', 'range_id'], 'number_allocations_range_fk')->references(['hq_id', 'range_id'])->on('consignment_number_ranges')->restrictOnDelete();
            $table->foreign(['hq_id', 'consignment_id'], 'number_allocations_consignment_fk')->references(['hq_id', 'consignment_id'])->on('consignments')->restrictOnDelete();
            $table->foreign('allocated_by')->references('user_id')->on('users')->restrictOnDelete();
            $table->unique('consignment_id', 'number_allocations_consignment_unique');
            $table->index(['hq_id', 'range_id', 'allocated_at', 'allocation_id'], 'number_allocations_history_index');
        });

        DB::statement("ALTER TABLE consignment_number_ranges ADD CONSTRAINT number_ranges_total_length_check CHECK (total_length BETWEEN 6 AND 28)");
        DB::statement("ALTER TABLE consignment_number_ranges ADD CONSTRAINT number_ranges_prefix_check CHECK (numeric_prefix REGEXP '^[0-9]+$' AND CHAR_LENGTH(numeric_prefix) < total_length)");
        DB::statement("ALTER TABLE consignment_number_ranges ADD CONSTRAINT number_ranges_serial_check CHECK (serial_start REGEXP '^[0-9]+$' AND serial_end REGEXP '^[0-9]+$' AND (next_serial IS NULL OR next_serial REGEXP '^[0-9]+$') AND CHAR_LENGTH(serial_start) = serial_width AND CHAR_LENGTH(serial_end) = serial_width AND (next_serial IS NULL OR CHAR_LENGTH(next_serial) = serial_width))");
        DB::statement("ALTER TABLE consignment_number_ranges ADD CONSTRAINT number_ranges_final_check CHECK (first_number REGEXP '^[0-9]{6,28}$' AND last_number REGEXP '^[0-9]{6,28}$' AND CHAR_LENGTH(first_number) = total_length AND CHAR_LENGTH(last_number) = total_length)");
        DB::statement("ALTER TABLE consignment_number_allocations ADD CONSTRAINT number_allocations_numeric_check CHECK (consignment_number REGEXP '^[0-9]{6,28}$')");
        DB::unprepared("CREATE TRIGGER consignment_number_allocations_immutable_update BEFORE UPDATE ON consignment_number_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment number allocation'");
        DB::unprepared("CREATE TRIGGER consignment_number_allocations_immutable_delete BEFORE DELETE ON consignment_number_allocations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment number allocation'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS consignment_number_allocations_immutable_update');
        DB::unprepared('DROP TRIGGER IF EXISTS consignment_number_allocations_immutable_delete');
        Schema::dropIfExists('consignment_number_allocations');
        Schema::dropIfExists('consignment_number_ranges');
        Schema::dropIfExists('consignment_number_range_registry');
    }
};
