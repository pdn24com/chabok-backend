<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_plans', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('consignment_id');
            $table->unsignedInteger('route_definition_id');
            $table->unsignedInteger('route_definition_version_id')->nullable();
            $table->enum('status', ['PLANNED', 'IN_PROGRESS', 'COMPLETED', 'SUPERSEDED'])->default('PLANNED');
            $table->char('active_slot', 64)->nullable();
            $table->unsignedInteger('version')->default('1');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'route_plans_hq_id_unique');
            $table->unique(['active_slot'], 'route_plans_active_slot_unique');
            $table->index(['hq_id', 'consignment_id'], 'route_plans_consignment_fk');
            $table->index(['hq_id', 'route_definition_id'], 'route_plans_definition_fk');
            $table->index(['created_by'], 'route_plans_created_by_foreign');
            $table->index(['hq_id', 'status', 'created_at'], 'route_plans_status_index');
            $table->index(['hq_id', 'route_definition_version_id'], 'route_plans_definition_version_fk');
            $table->foreign(['created_by'], 'route_plans_created_by_foreign')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'consignment_id'], 'route_plans_consignment_fk')->references(['hq_id', 'id'])->on('consignments')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_id'], 'route_plans_definition_fk')->references(['hq_id', 'id'])->on('route_definitions')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_id'], 'route_plans_definition_version_fk')->references(['hq_id', 'id'])->on('route_definition_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_plans');
    }
};
