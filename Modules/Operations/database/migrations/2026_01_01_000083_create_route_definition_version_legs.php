<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_definition_version_legs', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('route_definition_version_id');
            $table->unsignedSmallInteger('leg_order');
            $table->unsignedInteger('origin_node_id');
            $table->unsignedInteger('destination_node_id');
            $table->timestamp('created_at', 6)->nullable();
            $table->timestamp('updated_at', 6)->nullable();
            $table->unique(['route_definition_version_id', 'leg_order'], 'route_version_legs_order_unique');
            $table->unique(['hq_id', 'id'], 'route_version_legs_hq_id_unique');
            $table->index(['hq_id', 'route_definition_version_id'], 'route_version_legs_version_fk');
            $table->index(['hq_id', 'origin_node_id'], 'route_version_legs_origin_fk');
            $table->index(['hq_id', 'destination_node_id'], 'route_version_legs_destination_fk');
            $table->foreign(['hq_id', 'destination_node_id'], 'route_version_legs_destination_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'origin_node_id'], 'route_version_legs_origin_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
            $table->foreign(['hq_id', 'route_definition_version_id'], 'route_version_legs_version_fk')->references(['hq_id', 'id'])->on('route_definition_versions')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_definition_version_legs');
    }
};
