<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_teams', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->string('title', 200);
            // Technical reservation; building a new hierarchy is not required by this version.
            $table->unsignedInteger('parent_team_id')->nullable();
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->unsignedInteger('supervisor_user_id');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_teams_hq_internal_id_unique');
            $table->index(['hq_id', 'status'], 'crm_teams_hq_status_index');
            $table->index(['hq_id', 'parent_team_id'], 'crm_teams_parent_fk');
            $table->index(['supervisor_user_id'], 'crm_teams_supervisor_fk');
            $table->index(['created_by'], 'crm_teams_created_by_fk');
            $table->foreign(['created_by'], 'crm_teams_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'parent_team_id'], 'crm_teams_parent_fk')->references(['hq_id', 'id'])->on('crm_teams')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_teams_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['supervisor_user_id'], 'crm_teams_supervisor_fk')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_teams');
    }
};
