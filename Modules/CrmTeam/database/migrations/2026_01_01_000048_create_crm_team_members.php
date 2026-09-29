<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_team_members', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('team_id');
            $table->unsignedInteger('user_id');
            $table->timestamp('valid_from', 6);
            $table->timestamp('valid_to', 6)->nullable();
            $table->enum('status', ['ACTIVE', 'ENDED'])->default('ACTIVE');
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'id'], 'crm_team_members_hq_internal_id_unique');
            $table->index(['hq_id', 'team_id', 'status'], 'crm_team_members_team_status_index');
            $table->index(['hq_id', 'user_id', 'status'], 'crm_team_members_user_status_index');
            $table->index(['user_id'], 'crm_team_members_user_fk');
            $table->index(['created_by'], 'crm_team_members_created_by_fk');
            $table->foreign(['created_by'], 'crm_team_members_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'team_id'], 'crm_team_members_team_fk')->references(['hq_id', 'id'])->on('crm_teams')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_team_members_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['user_id'], 'crm_team_members_user_fk')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_team_members');
    }
};
