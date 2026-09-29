<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitment_schedule_scopes', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('commitment_schedule_version_id');
            $table->unsignedInteger('hq_id');
            $table->enum('scope_type', ['HQ', 'NODE']);
            $table->unsignedInteger('node_id')->nullable();
            $table->unique(['commitment_schedule_version_id', 'scope_type', 'node_id'], 'commitment_schedule_scope_unique');
            $table->index(['hq_id', 'node_id'], 'commitment_schedule_scopes_node_fk');
            $table->index(['hq_id', 'scope_type', 'node_id'], 'commitment_schedule_scope_lookup_index');
            $table->foreign(['commitment_schedule_version_id'], 'commitment_schedule_scopes_version_fk')->references(['id'])->on('commitment_schedule_versions')->onDelete('cascade');
            $table->foreign(['hq_id', 'node_id'], 'commitment_schedule_scopes_node_fk')->references(['hq_id', 'id'])->on('nodes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commitment_schedule_scopes');
    }
};
