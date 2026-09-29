<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activity_participants', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('hq_id');
            $table->unsignedInteger('activity_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('minutes')->nullable();
            $table->bigInteger('hourly_cost')->nullable();
            $table->date('rate_as_of')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamp('created_at', 6)->nullable();
            $table->unique(['hq_id', 'activity_id', 'user_id'], 'crm_activity_participants_user_unique');
            $table->unique(['hq_id', 'activity_id', 'customer_id'], 'crm_activity_participants_customer_unique');
            $table->index(['user_id'], 'crm_activity_participants_user_fk');
            $table->index(['created_by'], 'crm_activity_participants_created_by_fk');
            $table->foreign(['created_by'], 'crm_activity_participants_created_by_fk')->references(['id'])->on('users')->onDelete('restrict');
            $table->foreign(['hq_id', 'activity_id'], 'crm_activity_participants_activity_fk')->references(['hq_id', 'id'])->on('crm_activities')->onDelete('restrict');
            $table->foreign(['hq_id', 'customer_id'], 'crm_activity_participants_customer_fk')->references(['hq_id', 'id'])->on('crm_customers')->onDelete('restrict');
            $table->foreign(['hq_id'], 'crm_activity_participants_hq_id_fk')->references(['id'])->on('hq_tenants')->onDelete('restrict');
            $table->foreign(['user_id'], 'crm_activity_participants_user_fk')->references(['id'])->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activity_participants');
    }
};
