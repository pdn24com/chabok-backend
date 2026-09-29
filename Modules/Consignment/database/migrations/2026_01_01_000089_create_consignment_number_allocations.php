<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_number_allocations', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('range_id');
            $table->unsignedInteger('consignment_id');
            $table->string('consignment_number', 28);
            $table->unsignedInteger('allocated_by');
            $table->timestamp('allocated_at', 6)->useCurrent();
            $table->string('correlation_id', 64);
            $table->char('idempotency_key_hash', 64)->nullable();
            $table->unique(['consignment_number'], 'consignment_number_allocations_consignment_number_unique');
            $table->unique(['consignment_id'], 'number_allocations_consignment_unique');
            $table->index(['hq_id', 'consignment_id'], 'number_allocations_consignment_fk');
            $table->index(['allocated_by'], 'consignment_number_allocations_allocated_by_foreign');
            $table->index(['hq_id', 'range_id', 'allocated_at', 'id'], 'number_allocations_history_index');
            $table->foreign(['allocated_by'], 'consignment_number_allocations_allocated_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'consignment_id'], 'number_allocations_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'range_id'], 'number_allocations_range_fk')->references(['hq_id', 'id'])->on('consignment_number_ranges')->onDelete('restrict');
            $table->foreign(['hq_id'], 'consignment_number_allocations_hq_id_foreign')->references(['id'])->on('hq_tenants')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_number_allocations');
    }
};
